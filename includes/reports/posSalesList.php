<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_POSSales_Report extends WCWH_Listing 
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
			"order_no" 		=> "Order No.",
			"date" 			=> "Date",
			"status"		=> "Status",
			"register"		=> "Register",
			"session"		=> "Session",
			"cashier"		=> "Cashier",
			"payment_method"=> "Pay Method",
			"customer_name"	=> "Customer",
			"receipt_cus_serial"	=> "Code",
			"customer_code"	=> "Code",
			"employee_id"	=> "Employee_id",
			"amt_paid"		=> "Paid ({$currency})",
			"amt_change"	=> "Change ({$currency})",
			"amt_cash"		=> "Cash ({$currency})",
			"amt_credit"	=> "Credit ({$currency})",
			"order_total"	=> "Total ({$currency})",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'customer_code' );

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'order_no' => [ 'order_no', true ],
			'date' => [ 'date', true ],
			'customer_name' => [ 'customer_name', true ],
			'customer_code' => [ 'customer_code', true ],
			'receipt_cus_serial' => [ 'receipt_cus_serial', true ],
			'employee_id' => [ 'employee_id', true ],
			'order_total' => [ 'order_total', true ],
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
				<label class="" for="flag">By Customer </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], 'Select', [ 'guest'=>'Guest' ] );
                
		            wcwh_form_field( 'filter[customer]', 
		                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		            ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">By Payment Method </label><br>
				<?php
					$gateways = WC()->payment_gateways->get_available_payment_gateways();
					$options = [ ''=>'All' ];
					if( $gateways )
					{
						foreach( $gateways as $g_code => $vals )
						{
							$options[ $g_code ] = $vals->title;
						}
					}
                
		            wcwh_form_field( 'filter[payment_method]', 
		                [ 'id'=>'payment_method', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['payment_method'] )? $this->filters['payment_method'] : '', $view 
		            ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">By Order Status </label><br>
				<?php
					$options = [ ''=>'Paid', 'wc-cancelled'=>'Cancelled', 'wc-pending'=>'Pending' ];
                
		            wcwh_form_field( 'filter[order_status]', 
		                [ 'id'=>'order_status', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['order_status'] )? $this->filters['order_status'] : '', $view 
		            ); 
				?>
			</div>

		</div>
	<?php
	}
	
	public function get_action_btn( $item, $action = "view", $args = array() )
	{
		$btn = "";
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['name'];
		$id = ( $args['id'] )? $args['id'] : ( ( $item['doc_id'] )? $item['doc_id'] : $item['id'] );
		$serial = ( $args['serial'] )? $args['serial'] : $item['serial'];
		
		$attrs = array();
		$html_attr = "";
		if( !empty( $args['datas'] ) )
		{
			foreach( $args['datas'] as $key => $value )
			{
				$attrs[] = "data-{$key}='{$value}'";
			}
			if( $attrs )
			{
				$html_attr = implode( " ", $attrs );
			}
		}
		
		switch( $action )
		{
			case 'view':
			default:
				if( current_user_cans( [ 'view_pos_sales_detail_wh_reports' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" data-form="edit-pos_sales_report"
					data-modal="wcwhModalView" data-actions="close" data-title="'.$actions[ $action ].' '.$title.'"
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
		}

		return $btn;
	}
	
	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_paid = 0;
		$t_change = 0;
		$t_cash = 0;
		$t_credit = 0;
		$t_total = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_paid+= ( $data['amt_paid'] )? $data['amt_paid'] : 0;
				$t_change+= ( $data['amt_change'] )? $data['amt_change'] : 0;
				$t_cash+= ( $data['amt_cash'] )? $data['amt_cash'] : 0;
				$t_credit+= ( $data['amt_credit'] )? $data['amt_credit'] : 0;
				$t_total+= ( $data['order_total'] )? $data['order_total'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'order_no' ];
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
			if( $column_key == 'amt_paid' ) $column_display_name = round_to( $t_paid, 2, 1, 1 );
			if( $column_key == 'amt_change' ) $column_display_name = round_to( $t_change, 2, 1, 1 );
			if( $column_key == 'amt_cash' ) $column_display_name = round_to( $t_cash, 2, 1, 1 );
			if( $column_key == 'amt_credit' ) $column_display_name = round_to( $t_credit, 2, 1, 1 );
			if( $column_key == 'order_total' ) $column_display_name = round_to( $t_total, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_paid = 0;
		$t_change = 0;
		$t_cash = 0;
		$t_credit = 0;
		$t_total = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_paid+= ( $data['amt_paid'] )? $data['amt_paid'] : 0;
				$t_change+= ( $data['amt_change'] )? $data['amt_change'] : 0;
				$t_cash+= ( $data['amt_cash'] )? $data['amt_cash'] : 0;
				$t_credit+= ( $data['amt_credit'] )? $data['amt_credit'] : 0;
				$t_total+= ( $data['order_total'] )? $data['order_total'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'order_no' ];
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
			if( $column_key == 'amt_paid' ) $column_display_name = round_to( $t_paid, 2, 1, 1 );
			if( $column_key == 'amt_change' ) $column_display_name = round_to( $t_change, 2, 1, 1 );
			if( $column_key == 'amt_cash' ) $column_display_name = round_to( $t_cash, 2, 1, 1 );
			if( $column_key == 'amt_credit' ) $column_display_name = round_to( $t_credit, 2, 1, 1 );
			if( $column_key == 'order_total' ) $column_display_name = round_to( $t_total, 2, 1, 1 );

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

	public function column_order_no( $item ) 
	{	
		$actions['view'] = $this->get_action_btn( $item, 'view', [ 'services'=>'pos_sales_report_detail' ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['order_no'].'</strong>', $this->row_actions( $actions, true ) );  
	}
	
	public function column_status( $item )
	{
		$status = explode( '-', $item['status'] );
		return ucfirst( $status[1] );
	}

	public function column_payment_method( $item )
	{
		return ( $item['payment_method_title'] )? $item['payment_method_title'] : $item['payment_method'];
	}
	
	public function column_amt_paid( $item )
	{
		return ( $item['amt_paid'] != 0 )? round_to( $item['amt_paid'], 2, 1, 1 ) : '';
	}

	public function column_amt_change( $item )
	{
		return ( $item['amt_change'] != 0 )? round_to( $item['amt_change'], 2, 1, 1 ) : '';
	}
	
	public function column_amt_cash( $item )
	{
		return ( $item['amt_cash'] != 0 )? round_to( $item['amt_cash'], 2, 1, 1 ) : '';
	}

	public function column_amt_credit( $item )
	{
		return ( $item['amt_credit'] != 0 )? round_to( $item['amt_credit'], 2, 1, 1 ) : '';
	}
	
	public function column_order_total( $item )
	{
		return ( $item['order_total'] != 0 )? round_to( $item['order_total'], 2, 1, 1 ) : '';
	}
	
} //class