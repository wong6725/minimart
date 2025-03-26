<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_IntercomWorker_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $Customers = array();

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
			"order_no"		=> "No.",
			"ord_date"		=> "Date",
			"sap_customer"	=> "SAP Customer",
			"customer"		=> "Worker",
			"employee_id"	=> "Employee ID",
			"plant"			=> "Plant",
			"expense_type"	=> "Expense Type",
			//"remark"		=> "Remark",
			"amount"		=> "Amount ({$currency})",
		);

		$filters = $this->filters;

		return $cols;
	}

	/*public function get_header_cols()
	{
		return array(
			"order_no",
			"date",
			"customer",
			"remark",
		);
	}

	public function get_header_match()
	{
		return 'order_no';
	}*/

	public function get_hidden_column()
	{
		$col = [];

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'order_no' => [ 'order_no', true ],
			'date' => [ 'date', true ],
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
			<div class="col-md-3 segment">
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

			<div class="col-md-3 segment">
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
				<label class="" for="flag">By Customer </label><br>
				<?php
					if( $this->Customers )
					$options = options_data( $this->Customers, 'code', [ 'code', 'name' ], '' );
					
	                wcwh_form_field( 'filter[customer][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['customer'] )? $this->filters['customer'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-3 segment">
				<label class="" for="flag">By Expense Type </label><br>
				<?php
					$options = [ ''=>'All', 'grocery'=>'Groceries', 'tool'=>'Tools & Equipment' ];
					
	                wcwh_form_field( 'filter[expense_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options,
	                    ], 
	                    isset( $this->filters['expense_type'] )? $this->filters['expense_type'] : '', $view 
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
		
		$t_amount = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'order_no' ];
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
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amount, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_amount = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'order_no' ];
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

    public function column_sap_customer( $item ) 
	{
		$html = [];
		if( $item['sap_customer_code'] ) $html[] = $item['sap_customer_code'];
		if( $item['sap_customer_name'] ) $html[] = $item['sap_customer_name'];

		return implode( ' - ', $html );
    }

    public function column_customer( $item ) 
	{
		$html = [];
		if( $item['customer_code'] ) $html[] = $item['customer_code'];
		if( $item['customer_name'] ) $html[] = $item['customer_name'];

		return implode( ' - ', $html );
    }
	
	public function column_amount( $item )
	{
		return ( $item['amount'] )? round_to( $item['amount'], 2, 1, 1 ) : '';
	}
	
} //class