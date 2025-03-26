<?php
//Steven written
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSCredit_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_pos_credit";

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
		// $currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		return array(
            'cb'			=> '<input type="checkbox" />',
			"receipt"		=> "Order No.",
			"customer" 		    => "Customer",
			"uid"           => "UID",
            "time"      	=> "Order Time",
			"amount"	    => "Credit Amount",
            "credit_status" => "Status"
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
            'customer' => [ 'customer', true ],
			'uid' => [ 'uid', true ],
            'time' => [ 'time', true ],
			'amount' => [ 'amount', true ],
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
		if(DB_NAME == 'mndc'){
			return array(
				'1' => array(
					'view' => [ 'wcwh_user' ],
				),
				'0' => array(
					'view' => [ 'wcwh_user' ],
				),
			);
		}
		return array(
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support', 'manage_pos_order' ],
				'delete' => [ 'wh_admin_support', 'manage_pos_order' ],
			),
            '0' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support', 'manage_pos_order' ],
				'restore' => [ 'wh_admin_support', 'manage_pos_order' ],
			),
		);
	}
	

	public function filter_search()
	{
	?>
		<div class="row">
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

    public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['receipt'] );
		
		return $html;
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
