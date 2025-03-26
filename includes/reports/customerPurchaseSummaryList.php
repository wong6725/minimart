<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_CustomerPurchaseSummary_Report extends WCWH_Listing 
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
			"customer_name" => "Name",
			"customer_no"	=> "Customer No",
			"employee_id"	=> "Employee ID",
			"acc_type"		=> "Acc type",
			"position"		=> "Position",
			"credit_group"	=> "Credit Group",
			"total_creditable"	=> "Available Credit",
			"credit_amt"		=> "Used Credit",
		);
	}


	public function get_hidden_column()
	{
		$col = [ 'position', 'credit_group' ];

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'customer_name' => [ 'customer_name', true ],
			'customer_no' => [ 'customer_no', true ],
			'employee_id' => [ 'employee_id', true ],
			'total_creditable' => [ 'total_creditable', true ],
			'credit_amt' => [ 'credit_amt', true ],
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
		</div>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Account Type</label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_account_type', $filters, [], false, ['sap_only'=>1] ), 'id', [ 'code' ], '' );
                
					wcwh_form_field( 'filter[acc_type][]', 
	                    [ 'id'=>'acc_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['acc_type'] )? $this->filters['acc_type'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Job / Position</label><br>
				<?php
					$filter = [];
					if( $this->seller ) $filter['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer_job', $filter, [], false, [] ), 'id', [ 'name' ], '' );
                
	                wcwh_form_field( 'filter[cjob][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['cjob'] )? $this->filters['cjob'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
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
		</div>
	<?php
	}

	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_amt = 0;
		$total_creditable = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_amt+= ( $data['credit_amt'] )? $data['credit_amt'] : 0;
				$total_creditable+= ( $data['total_creditable'] )? $data['total_creditable'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'customer_name', 'customer_no', 'employee_id', 'acc_type' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag  	= ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope	= ( 'th' === $tag ) ? 'scope="col"' : '';
			$id   	= $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'Subtotal:';
			if( $column_key == 'credit_amt' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'total_creditable' ) $column_display_name = round_to( $total_creditable, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_amt = 0;
		$total_creditable = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_amt+= ( $data['credit_amt'] )? $data['credit_amt'] : 0;
				$total_creditable+= ( $data['total_creditable'] )? $data['total_creditable'] : 0;
			}
		}
		
		$t_total = 0;
		if( $totals )
		{
			foreach( $totals as $id => $t )
			{
				$t_total+= $t['total_used'];
			}
		}

		$colspan = 'no';
		$colnull = [ 'customer_name', 'customer_no', 'employee_id', 'acc_type' ];
		foreach ( $columns as $column_key => $column_display_name ) 
		{
			if( $colnull && in_array( $column_key, $colnull ) ) continue;

			$class = array( 'manage-column', "column-$column_key", $column_key );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			$span	= ( $colspan === $column_key )? 'colspan="'.( sizeof( $colnull )+1 ).'"' : '';
			$tag  	= ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope	= ( 'th' === $tag ) ? 'scope="col"' : '';
			$id   	= $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}
			
			$column_display_name = '';
			if( $column_key == 'no' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'credit_amt' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'total_creditable' ) $column_display_name = round_to( $total_creditable, 2, 1, 1 );

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

	public function column_total_creditable( $item )
	{
		return round_to( $item['total_creditable'], 2, 1, 1 );
	}

	public function column_credit_amt( $item )
	{
		return round_to( $item['credit_amt'], 2, 1, 1 );
	}

} //class