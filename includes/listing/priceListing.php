<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Price_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_pricing";

	public $offCost = false;

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

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}
	
	public function get_columns() 
	{
		return array(
			'cb'			=> '<input type="checkbox" />',
			"name" 			=> "Name",
			"uom_code"		=> "UOM",
			"serial"		=> "Barcode",
			"item_group"	=> "Group",
			"brand"			=> "Brand",
			"category"		=> "Category",
			"parent" 		=> "Parent",
			"docno"			=> "Price Doc",
			"price_type"	=> "Type",
			"avg_cost"		=> "AVG Cost",
			"latest_cost"	=> "Latest Cost",
			"uprice"		=> "Price",
			"margin"		=> "Margin (%)",
			"metric_price"	=> "Metric(kg/l) Price",
			"unit_price"	=> "Selling Price",
		);
	}

	public function get_hidden_column()
	{
		$col = [ "parent", "type" ];
		if( $this->offCost )
		{
			$col[] = "avg_cost";

			//if( ! current_user_cans( ['wh_support'] ) )
				$col[] = "latest_cost";
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'name' => [ "name", true ],
			'serial' => [ "code", true ],
			'item_group' => [ "grp_code", true ],
			'brand' => [ "brand_code", true ],
			'category' => [ "cat_code", true ],
			'docno' => [ "docno", true ],
			'unit_price' => [ "unit_price", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'save_'.$this->section_id ) )
			$actions['wh_pricing'] = "New Pricing";
		
		return $actions;
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		$this->search_box( 'Search', "s" );
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
			)
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
		<?php
			$sellers = apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1 ] );
			$options = options_data( $sellers, 'code', [ 'code', 'name' ], '' );
			if( $options ):
				$idx = 0;
				foreach( $sellers as $i => $seller )
				{
					if( $seller['indication'] )
					{
						$idx = $i;
					}
				}
				$keys = array_keys( $options );
				$this->filters['seller'] = empty( $this->filters['seller'] )? $keys[$idx] : $this->filters['seller'];
		?>
			<div class="segment col-md-4">
				<label class="" for="flag">By Seller <sup>Current: <?php echo $options[$this->filters['seller']]; ?></sup></label>
				<?php
					wcwh_form_field( 'filter[seller]', 
	                    [ 'id'=>'seller', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options, 'offClass'=>true
	                    ], 
	                    isset( $this->filters['seller'] )? $this->filters['seller'] : '', $view 
	                ); 
				?>
			</div>
		<?php endif; ?>

		<?php
			$options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
			if( $options ):
		?>
			<div class="segment col-md-4">
				<label class="" for="flag">By Client</label>
				<?php
					wcwh_form_field( 'filter[client_code]', 
	                    [ 'id'=>'client_code', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
	                        'options'=> $options, 'offClass'=>true
	                    ], 
	                    isset( $this->filters['client_code'] )? $this->filters['client_code'] : '', $view 
	                ); 
				?>
			</div>
		<?php endif; ?>

			<div class="segment col-md-4">
				<label class="" for="flag">Price On Date</label><br>
				<?php
					wcwh_form_field( 'filter[on_date]', 
	                    [ 'id'=>'on_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'offClass'=>true
	                    ], 
	                    isset( $this->filters['on_date'] )? $this->filters['on_date'] : '', $view 
	                ); 
				?>
			</div>
		</div>

		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Items</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['id'] )? $this->filters['id'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Group</label><br>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_item_group', [], [], false, [] ), 'id', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[grp_id][]', 
	                    [ 'id'=>'grp_id', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['grp_id'] )? $this->filters['grp_id'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Category</label><br>
				<?php
					$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
	                $options = options_data( (array) $terms, 'term_id', [ 'slug', 'name' ], '' );
	                
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'category', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Inconsistent Metric (kg/l)</label><br>
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
			<div class="segment col-md-4">
				<label class="" for="flag">By Price</label><br>
				<?php
	                $options = [ ''=> 'All Price', 'yes'=>'Has Price', 'no'=>'No Price' ];
	                
	                wcwh_form_field( 'filter[pricing]', 
	                    [ 'id'=>'pricing', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['pricing'] )? $this->filters['pricing'] : '', $view 
	                ); 
				?>
			</div>
		</div>
	<?php
	}

	public function row_class( $class, $item )
	{
		if( ! $item['unit_price'] || $item['unit_price'] <= 0 )
			$class[] = "row-red";

		return $class;
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

	public function column_name( $item ) 
	{	
		//$actions = $this->get_actions( $item, $item['status'] );

		$html = $item['name'];
		$args = [ 'id'=>$item['id'], 'service'=>'wh_items_action', 'title'=>$html, 'permission'=>[ 'access_wh_items' ] ];
		$html = $this->get_external_btn( $html, $args );
		
		$indent = "";
		if( $item['breadcrumb_id'] && $item['status'] )
		{	
			if( $item['breadcrumb_status'] ) $stat = explode( ",", $item['breadcrumb_status'] );
			$ids = explode( ",", $item['breadcrumb_id'] );
			foreach( $ids as $i => $id )
			{
				if( $id == $item['id'] || $stat[ $i ] <= 0 ) continue;
				$indent.= "— ";
			}
		}
		$indent = ( $indent )? "<span class='displayBlock'>{$indent}</span>" : "";

		$indent = "<span class='displayBlock'>{$indent}</span>";

		return sprintf( '%1$s %2$s', $indent.'<strong>'.$html.'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_serial( $item )
	{
		$code = [ 'code'=>$item['code'], 'sku'=>$item['_sku'], 'barcode'=>$item['serial'] ];
		$segments = [];
		foreach( $code as $key => $val )
		{
			if( ! $val ) continue;

			$segments[] = "<span class='toolTip' title='{$key}'>{$val}</span>";
		}

		$html = implode( ", ", $segments );
		
		return $html;
	}

	public function column_parent( $item )
	{
		if( $item['breadcrumb_id'] ) $ids = explode( ",", $item['breadcrumb_id'] );
		if( $item['breadcrumb_status'] ) $stat = explode( ",", $item['breadcrumb_status'] );
		if( $item['breadcrumb_code'] ) $codes = explode( ",", $item['breadcrumb_code'] );

		$elem = [];
		if( $codes )
		foreach( $codes as $i => $code )
		{
			if( $ids[ $i ] == $item['id'] || $stat[ $i ] <= 0 ) continue;
			$args = [ 'id'=>$ids[ $i ], 'service'=>'wh_items_action', 'title'=>$code ];
			$elem[] = $this->get_external_btn( $code, $args );
		}
		if( $elem ) $elem[] = $item['code'];
		$html = implode( " > ", $elem );
		
		return $html;
	}

	public function column_category( $item )
	{
		$html = $item['cat_code'].' - '.$item['cat_name'];
		$args = [ 'id'=>$item['category'], 'service'=>'wh_items_category_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_category' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_item_group( $item )
	{
		$html = $item['grp_code'].' - '.$item['grp_name'];
		$args = [ 'id'=>$item['grp_id'], 'service'=>'wh_items_group_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_group' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_store_type( $item )
	{
		$html = $item['store_code'].' - '.$item['store_name'];
		$args = [ 'id'=>$item['store_type_id'], 'service'=>'wh_items_store_type_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_store_type' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_brand( $item )
	{
		$html = $item['brand_code'].' - '.$item['brand_name'];
		$args = [ 'id'=>$item['brand_id'], 'service'=>'wh_brand_action', 'title'=>$html, 'permission'=>[ 'access_wh_brand' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_docno( $item )
	{
		$html = $item['docno'];
		$args = [ 'id'=>$item['price_id'], 'service'=>'wh_pricing_action', 'title'=>$html, 'permission'=>[ 'access_wh_pricing' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_metric_price( $item )
	{
		$html = '';
		if( $item['required_unit'] )
		{
			$html = round_to( $item['avg_unit_price'], 2, 1, 1 ). '<span class="toolTip" title="Avg Metric(kg/l)"> ('.$item['average_in_unit'].')</span>';
		}

		return $html;
	}

	public function column_unit_price( $item )
	{
		$price = ( $item['unit_price'] > 0 )? $item['unit_price'] : "0.00";
		$class = ( $item['unit_price'] > 0 )? "" : "clr-red";

		$html = '<strong class="'.$class.'">'.$price.'</strong>';

		return $html;
	}
	
} //class