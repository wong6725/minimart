<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSCDN_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_pos_cdn";

	private $users;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->users = get_simple_users();
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
			'cb'		=> '<input type="checkbox" />',
			"order_no" 	=> "Order No.",
			"session_id"=> "Session",
			"register"	=> "Register",
			"order_type"=> "Type",
			"customer" 	=> "Customer",
			"order_date" => "Date",
			"order_status"	=> "Status",
			"item"		=> "Item",
			"payment_method_title" => "Payment Method",
			"total"		=> "Amount",
			"remark"	=> "Remark",
		);
	}

	public function get_hidden_column()
	{
		$col = [];
		if( ! $this->useFlag ) $col[] = 'approval';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'order_no' => [ "order_no", true ],
			'customer' => [ "customer_code", true ],
			'order_date' => [ "order_date", true ],
			'total' => [ "total", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = [];
		
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

	public function get_statuses()
	{
		return array(
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'wc-processing'		=> array( 'key' => 'processing', 'title' => 'Processing' ),
			'wc-pending'		=> array( 'key' => 'pending', 'title' => 'Pending' ),
			'wc-on-hold'		=> array( 'key' => 'on-hold', 'title' => 'Confirmation' ),
			'wc-completed'		=> array( 'key' => 'completed', 'title' => 'Completed' ),
			'wc-cancelled'		=> array( 'key' => 'cancelled', 'title' => 'Cancelled' ),
			'wc-refunded'		=> array( 'key' => 'refunded', 'title' => 'Refunded' ),
			'wc-failed'			=> array( 'key' => 'failed', 'title' => 'Failed' ),
		);
	}

	public function get_status_action( $item )
	{
		return array(
			'default' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support', 'manage_pos_cdn' ],
				'delete' => [ 'wh_admin_support', 'manage_pos_cdn' ],
			),
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="col-md-4">
				<label class="" for="flag">By Customer </label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, ['usage'=>0] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], 'Select', [ 'guest'=>'Guest' ], '' );
                
		            wcwh_form_field( 'filter[customer][]', 
		                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		            ); 
				?>
			</div>

			<div class="col-md-4">
				<label class="" for="flag">By Items</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[product_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['product_id'] )? $this->filters['product_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-4">
				<label class="" for="flag">By Order Type</label>
				<?php
					$options = [ ''=>'All', 'tool'=>'Tool & Equipment' ];
                
	                wcwh_form_field( 'filter[order_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['order_type'] )? $this->filters['order_type'] : '', $view 
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
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

	public function column_order_no( $item ) 
	{	
		$actions = [];
		$actions['view'] = $this->get_action_btn( $item, 'view' );

		if( in_array( $item['order_status'], [ 'wc-pending', 'wc-on-hold' ] ) )
			$actions['edit'] = $this->get_action_btn( $item, 'edit' );

		if( in_array( $item['order_status'], [ 'wc-pending', 'wc-on-hold' ] ) )
			$actions['delete'] = $this->get_action_btn( $item, 'delete' );

		$item['flag'] = 1;
		if( in_array( $item['order_status'], [ 'wc-pending', 'wc-on-hold' ] ) )
			$actions['post'] = $this->get_action_btn( $item, 'post' );

		if( in_array( $item['order_status'], [ 'wc-processing', 'wc-completed' ] ) )
			$actions['unpost'] = $this->get_action_btn( $item, 'unpost' );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['order_no'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_customer( $item )
	{
		$html = $item['customer_code']." - ".$item['customer_name'];

		return $html;
	}

	public function column_order_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['order_status'] ]['key']}'>{$statuses[ $item['order_status'] ]['title']}</span>";

		return $html;
	}

	public function column_remark( $item )
	{
		$html = [];
		if( $item['order_comments'] ) $html[] = "Order Remark: ".$item['order_comments'];
		if( $item['cancel_remark'] ) $html[] = "Cancel Remark: ".$item['cancel_remark'];

		return ( $html )? implode( "<br>",$html ) : "";
	}
	
} //class