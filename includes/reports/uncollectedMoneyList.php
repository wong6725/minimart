<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_UncollectedMoney_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $overall = array();

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
			"docno"			=> "Document",
			"date" 			=> "Doc Date",
			"from_period"	=> "From Period",
			"to_period"		=> "To Period",
			"cash_sales"	=> "Cash Sales", 
			"collector"		=> "Collector",
			"collected_amt" => "Collected Amt",
			"balance"		=> "Leftover Balance",
		);
	}

	public function get_hidden_column()
	{
		$col = array();

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'docno' => [ 'docno', true ],
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
	<?php
	}
	
	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		//if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_amt = 0;
		$t_cash = 0;
		$t_bal = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_cash+= ( $data['cash_sales'] )? $data['cash_sales'] : 0;
				$t_amt+= ( $data['collected_amt'] )? $data['collected_amt'] : 0;
				$t_bal+= ( $data['balance'] )? $data['balance'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'date','docno' ];
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
			if( $column_key == 'no' ) $column_display_name = '<b>Total:</b>';
			if( $column_key == 'cash_sales' ) $column_display_name = '<b>'.round_to( $t_cash, 2, 1, 1 ).'</b>';
			if( $column_key == 'collected_amt' ) $column_display_name = '<b>'.round_to( $t_amt, 2, 1, 1 ).'</b>';
			if( $column_key == 'balance' ) $column_display_name = '<b>'.round_to( $t_bal, 2, 1, 1 ).'</b>';
			
			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		if( ! $this->overall ) return;
		$overall = $this->overall;

		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_cash+= ( $data['cash_sales'] )? $data['cash_sales'] : 0;
				$t_amt+= ( $data['collected_amt'] )? $data['collected_amt'] : 0;
				$t_bal+= ( $data['balance'] )? $data['balance'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'date', 'docno' ];
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
			if( $column_key == 'no' ) $column_display_name = '<b>Overall:</b>';
			if( $column_key == 'from_period' ) $column_display_name ='<b> '. $overall['from_period'].'</b>';
			if( $column_key == 'to_period' ) $column_display_name ='<b> '. $overall['to_period'].'</b>';
			if( $column_key == 'cash_sales' ) $column_display_name ='<b> '.round_to( $overall['cash_sales'], 2, 1, 1 ).'</b>';

			if( $column_key == 'collected_amt' ) $column_display_name = '<b>'.round_to( $t_amt, 2, 1, 1 ).'</b>';
			if( $column_key == 'balance' ) $column_display_name = '<b>'.round_to( $overall['cash_sales'] - $t_amt, 2, 1, 1 ).'</b>';

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

	public function column_from_preiod( $item ) 
	{	
		return sprintf( '%1$s', '<strong>'.$item['from_preiod'].'</strong>' );  
	}

	public function column_to_preiod( $item ) 
	{	
		return sprintf( '%1$s', '<strong>'.$item['to_period'].'</strong>' );  
	}

	public function column_docno( $item )
	{
		return $item['docno']; 
	}

	public function column_cash_sales( $item )
	{
		return round_to( $item['cash_sales'], 2, 1, 1 );
	}

	public function column_collected_amt( $item )
	{
		return round_to( $item['collected_amt'], 2, 1, 1 );
	}

	public function column_balance( $item )
	{
		return round_to( $item['balance'], 2, 1, 1 );
	}

	public function column_bankin_amt( $item )
	{
		return round_to( $item['bankin_amt'], 2, 1, 1 );
	}
} //class