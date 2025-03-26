<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Bank_In_Sumamary_report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;
	public $doc_opts = [];
	public $bank_opts = [];

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
		return array(
			'no' => '',
			'date' => 'Date',
			"convert_amt_currency" => "Converted Amount Currency",
			'transaction' => 'Transaction',
			'amount' => "Amount (RM)",
			'exchange_rate' => 'Exchange Rate (Avg)',
			'convert_amount' => "Convert Amount",
			'service_charge' => "Service Charge (RM)",
			'total_amount' => "Total Amount (RM)"
		);
	}

	public function get_hidden_column()
	{
		$col = array();

		if( ! current_user_cans( [ 'wh_admin_support' ] ) ) $col[] = 'remark';

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'convert_amt_currency' => ['convert_amt_currency',true],
			'transaction' => ['transaction', true],
			'amount' => ['amount', true],
			'exchange_rate' => ['exchange_rate', true],
			'convert_amount' => ['convert_amount', true],
			'service_charge' => ['service_charge', true],
			'total_amount' =>  ['total_amount', true],
		];

		return $cols;
	}

	public function get_bulk_actions() 
	{
		$actions = [];
		
		return $actions;
	}

	public function no_items() 
	{
		echo "<strong class='font16'>Please Submit for Report Generating / Nothing found </strong>";
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
			<div class="segment col-md-4">
				<label class="" for="flag">Date Type </label>
				<?php
					$options =  [ 'doc_date'=>'Document Date', 'post_date'=>' Posting Date' ];
                
	                wcwh_form_field( 'filter[date_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict', 'modalSelect'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['date_type'] )? $this->filters['date_type'] : 'post_date', $view 
	                ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">From Date <sup style='font-size:70%;'>Current: <?php echo $this->filters['from_date']; ?></sup></label><br>
				<?php
					wcwh_form_field( 'filter[from_date]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $from_date )? $from_date : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">To Date <sup style='font-size:70%;'>Current: <?php echo $this->filters['to_date']; ?></sup></label><br>
				<?php
					wcwh_form_field( 'filter[to_date]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $to_date )? $to_date : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Convert Amount Currency <sup style='font-size:70%;'>Default: All</sup></label>
				<?php
					$currency = get_woocommerce_currencies();

					$options = options_data( $currency,'',array(),'','' );
                
	                wcwh_form_field( 'filter[by_currency][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>true, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['by_currency'] )? $this->filters['by_currency'] : '', $view 
	                );
				?>
			</div>
		</div>
	<?php
	}
	
	/*
		public function print_column_footers( $datas = array(), $all_datas = array() ) 
		{
			if( count( $all_datas ) < $this->args['per_page_row'] ) return;

			list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
			
			$t_qty = 0;
			$t_foc = 0;
			$t_amt = 0;
			$t_fqty = 0;
			$t_famt = 0;
			
			if( $datas )
			{
				foreach( $datas as $i => $data )
				{
					$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
					$t_foc+= ( $data['foc'] )? $data['foc'] : 0;
					$t_amt+= ( $data['line_amount'] )? $data['line_amount'] : 0;
					$t_fqty+= ( $data['fin_qty'] )? $data['fin_qty'] : 0;
					$t_famt+= ( $data['fin_amount'] )? $data['fin_amount'] : 0;
				}
			}

			$colspan = 'no';
			$colnull = [ 'docno', 'doc_date', 'created_at' ];
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
				if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
				if( $column_key == 'foc' ) $column_display_name = round_to( $t_foc, 2, 1, 1 );
				if( $column_key == 'line_amount' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
				if( $column_key == 'fin_qty' ) $column_display_name = round_to( $t_fqty, 2, 1, 1 );
				if( $column_key == 'fin_amount' ) $column_display_name = round_to( $t_famt, 2, 1, 1 );

				echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
			}
		}
	*/

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_transaction = 0;
		$t_amount = 0;
		$avg_exchange = 0;
		$t_convert_amount = 0;
		//$avg_charge=0;
		$t_total_amount = 0;

		$count = 0;

		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$count++;
				$t_transaction+= ( $data['transaction'] )? $data['transaction'] : 0;
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
				$avg_exchange+= ( $data['total_exchange_rate'] )? $data['total_exchange_rate'] : 0;
				$t_convert_amount+= ( $data['convert_amount'] )? $data['convert_amount'] : 0;
				$avg_charge+= ( $data['service_charge'] )? $data['service_charge'] : 0;
				$t_total_amount+= ( $data['total_amount'] )? $data['total_amount'] : 0;
			}

			$avg_exchange = $avg_exchange/$t_transaction;
			//$avg_charge = $avg_charge/$t_transaction;
		}

		$colspan = 'no';
		$colnull = [ 'date' ];
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
			if( $column_key == 'transaction' ) $column_display_name = round_to( $t_transaction, 0, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amount, 2, 1, 1 );
			if( $column_key == 'exchange_rate' ) $column_display_name = round_to( $avg_exchange, 2, 1, 1 )." (AVG)";
			if( $column_key == 'convert_amount' ) $column_display_name = round_to( $t_convert_amount, 2, 1, 1 );
			if( $column_key == 'service_charge' ) $column_display_name = round_to( $avg_charge, 2, 1, 1 );
			if( $column_key == 'total_amount' ) $column_display_name = round_to( $t_total_amount, 2, 1, 1 );

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

	public function column_date( $item ) 
	{	
		return sprintf( '%1$s', '<strong>'.$item['date'].'</strong>' );  
	}

	public function column_convert_amt_currency( $item ) 
	{	
		return $item['convert_amt_currency'];
	}
	
	public function column_transactions( $item )
	{
		return round_to( $item['transactions'], 0, 1, 1 );
	}

	public function column_amount( $item )
	{
		return round_to( $item['amount'], 2, 1, 1 );
	}

	public function column_exchange_change( $item )
	{
		return round_to( $item['exchange_change'], 2, 1, 1 );
	}

	public function column_convert_amount( $item )
	{
		return round_to( $item['convert_amount'], 2, 1, 1 );
	}

	public function column_service_charge( $item )
	{
		return round_to( $item['service_charge'], 2, 1, 1 );
	}

	public function column_total_amount( $item )
	{
		return round_to( $item['total_amount'], 2, 1, 1 );
	}
   
	
} //class