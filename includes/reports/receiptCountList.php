<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_ReceiptCount_Report extends WCWH_Listing 
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
			"customer_qr" 	=> "Customer QR",
			"employee_id"	=> "Employee ID",
			"name" 			=> "Name",
			"acc_type"		=> "Acc Type",
			"acc_id"		=> "Acc ID",
			"transaction"	=> "No. of Receipt",
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
			'customer_qr' => [ 'customer_qr', true ],
			'employee_id' => [ 'employee_id', true ],
			'name' => [ 'name', true ],
			'transaction' => [ 'transaction', true ],
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
	?>
		<div class="row">
			<div class="col-md-4 segment">
				<label class="" for="flag">By Customer </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, ['uid'=>3, 'usage'=>0] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '' );
                
		            wcwh_form_field( 'filter[customer][]', 
		                [ 'id'=>'customer', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
		            ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">Acc Type</label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_account_type', $filters, [], false, ['sap_only'=>1] ), 'id', [ 'code' ] );
                
					wcwh_form_field( 'filter[acc_type]', 
	                    [ 'id'=>'acc_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['acc_type'] )? $this->filters['acc_type'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">Listing QR By</label><br>
				<?php
					$options = [
						'latest' => 'Latest In Use',
						'history' => 'Past Used',
						'all' => 'All',
					];
                
					wcwh_form_field( 'filter[list_type]', 
	                    [ 'id'=>'list_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['selectStrict'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['list_type'] )? $this->filters['list_type'] : '', $view 
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
		
		$t = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t+= ( $data['transaction'] )? $data['transaction'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'customer_qr' ];
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
			if( $column_key == 'transaction' ) $column_display_name = round_to( $t, 0, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t+= ( $data['transaction'] )? $data['transaction'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'customer_qr' ];
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
			if( $column_key == 'transaction' ) $column_display_name = round_to( $t, 0, 1, 1 );

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

	public function column_transaction( $item )
	{
		return round_to( $item['transaction'], 0, 1, 1 );
	}
	
} //class