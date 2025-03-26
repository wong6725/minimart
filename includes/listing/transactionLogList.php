<?php
//Steven written
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TransactionLog_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_transaction_log_rpt";

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
		$cols = array(
			'no'			=> '',
			"in_doc"		=> "IN Doc",
			"out_doc"		=> "OUT Doc",
			"warehouse_id"	=> "Outlet",
			'name'			=> 'Product',
			'category'		=> 'Category',
			'_uom_code'		=> 'UOM',
			"bqty"			=> "Qty",
			"deduct_qty"	=> "OUT Qty",
			"outstanding"	=> 'Balance Qty',
			"lupdate_at" 	=> "Time",
		);

		$filters = $this->filters;

		return $cols;
	}

	public function get_hidden_column()
	{
		return [ 'warehouse' ];
	}

	public function get_sortable_columns()
	{
		$cols = [
			'in_doc' => [ 'in_doc', true ],
			'out_doc' => [ 'out_doc', true ],
			'name' => [ 'name', true ],
			'category' => [ 'category', true ],
			'lupdate_at' => [ 'lupdate_at', true ],
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


    // public function get_status_action( $item )
	// {
	// 	return array(
	// 		'1' => array(
	// 			'view' => [ 'wcwh_user' ],
	// 			'edit' => [ 'wh_admin_support', 'manage_pos_order' ],
	// 			'delete' => [ 'wh_admin_support', 'manage_pos_order' ],
	// 		),
    //         '0' => array(
	// 			'view' => [ 'wcwh_user' ],
	// 			'edit' => [ 'wh_admin_support', 'manage_pos_order' ],
	// 			'restore' => [ 'wh_admin_support', 'manage_pos_order' ],
	// 		),
	// 	);
	// }
	

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="col-md-6 segment">
				<label class="" for="flag">By Category </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_category', $filters, [], false, [] ), 'name', [ 'slug', 'name' ], '' );
					
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
					$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'name', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					
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
 

    public function column_outstanding( $item ) 
	{
		$item['outstanding'] = round($item["bqty"] - $item['deduct_qty'],2); 
		
		return $item['outstanding'];
    }

    public function column_receipt( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['credit_status'] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['receipt'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_warehouse( $item ) 
	{	
		if( !empty( $item['warehouse_id'] ) ) $html[] = $item['warehouse_id'];
		if( !empty( $item['storage_code'] ) ) $html[] = $item['storage_code'];
		if( !empty( $item['storage_name'] ) ) $html[] = $item['storage_name'];

		return implode( ' - ', $html );  
	}
	

	public function column_amount( $item )
	{
		return $item['amount'];
	}

    public function column_credit_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['credit_status'] ]['key']}'>{$statuses[ $item['credit_status'] ]['title']}</span>";

		return $html;
	}

	
} //class
