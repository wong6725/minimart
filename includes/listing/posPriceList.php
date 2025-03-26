<?php
//Steven written
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSPrice_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_pos_session";

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

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}
	
	public function get_columns() 
	{
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		return array(
			"receipt"		=> "Receipt",
			"sales_item_id"	=> "Item ID",
			"warehouse" 	=> "Warehouse",
			"customer" 		=> "Customer",
			"sales_date"	=> "Date",
			"category"		=> "Category",
			"product"		=> "Product",
			"uom"			=> "UOM",
			"qty"			=> "Qty",
			"unit"			=> "Metric (kg/l)",
			"uprice"		=> "UPrice",
			"price"			=> "Price",
			"total_amount"	=> "Amt",
			"status"		=> "Status",
		);
	}

	public function get_hidden_column()
	{
		return [ 'warehouse' ];
	}

	public function get_sortable_columns()
	{
		$cols = [
			'receipt' => [ 'receipt', true ],
			'sales_item_id' => [ 'item_id', true ],
			'sales_date' => [ 'sales_date', true ],
			'customer' => [ 'customer_code', true ],
			'product' => [ 'item_code', true ],
			'total_amount' => [ 'total_amount', true ],
		];

		return $cols;
	}

	public function get_bulk_actions() 
	{
		$actions = array();
		
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
			),
		);
	}
	
	public function get_statuses()
	{
		return array(
			'1'		=> array( 'key' => 'yes', 'title' => 'Success' ),
			'0'		=> array( 'key' => 'no', 'title' => 'Cancelled' ),
			//'-1'	=> array( 'key' => 'deleted', 'title' => 'Deleted' ),
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
			<div class="col-md-4">
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

			<div class="col-md-4">
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

			<div class="col-md-6">
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

			<div class="col-md-6">
				<label class="" for="flag">By Customer </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '', [ 'guest'=>'Guest' ] );
					
	                wcwh_form_field( 'filter[customer][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
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
	public function column_sales_item_id( $item ) 
	{
		return $item['item_id'];
    }

    public function column_receipt( $item ) 
	{
		return $item['receipt'];
    }

	public function column_warehouse( $item ) 
	{	
		if( !empty( $item['warehouse_id'] ) ) $html[] = $item['warehouse_id'];
		if( !empty( $item['storage_code'] ) ) $html[] = $item['storage_code'];
		if( !empty( $item['storage_name'] ) ) $html[] = $item['storage_name'];

		return implode( ' - ', $html );  
	}
	
	public function column_customer( $item )
	{
		$html = [];
		
		if( !empty( $item['customer_code'] ) ) $html[] = $item['customer_code'];
		if( !empty( $item['employee_id'] ) ) $html[] = $item['employee_id'];
		if( !empty( $item['customer_name'] ) ) $html[] = $item['customer_name'];

		return implode( ' - ', $html ); 
	}
	
	public function column_sales_date( $item )
	{
		return $item['sales_date'];
	}

	public function column_category( $item )
	{
		$html = [];

		if( !empty( $item['category_name'] ) ) $html[] = $item['category_name'];
		if( !empty( $item['category_code'] ) ) $html[] = $item['category_code'];

		return implode( ' - ', $html );
	}

	public function column_product( $item )
	{
		return $item['item_code'].' - '.$item['item_name'];
	}
	
	public function column_uom( $item )
	{
		return $item['uom'];
	}

	public function column_qty( $item )
	{
		return $item['qty'];
	}

	public function column_unit( $item )
	{
		return $item['unit'];
	}

	public function column_uprice( $item )
	{
		return $item['uprice'];
	}

	public function column_price( $item )
	{
		return $item['price'];
	}

	public function column_total_amount( $item )
	{
		return $item['total_amount'];
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}
	
} //class