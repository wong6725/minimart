<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_CustomerCreditLimit_Report extends WCWH_Listing 
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
			"customer_name" => "Customer Name",
			"customer_no"	=> "Customer No",
			"sap_emp_id" 	=> "SAP Emp ID",
			"acc_type"		=> "Acc Type",
			"from_date"		=> "From Date",
			"to_date"		=> "To Date",
			"credit_limit"	=> "Credit Limit",
			"topup"			=> "Top Up",
			"total_creditable" => "Total Creditable",
			"total_used"	=> "Total Used",
			"balance"		=> "Balance",
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
			'customer_name' => [ 'customer_name', true ],
			'customer_no' => [ 'customer_no', true ],
			'sap_emp_id' => [ 'sap_emp_id', true ],
			'total_creditable' => [ 'total_creditable', true ],
			'credit_limit' => [ 'credit_limit', true ],
			'topup' => [ 'topup', true ],
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
			<div class="col-md-6 segment">
				<label class="" for="flag">Acc Type</label><br>
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

			<div class="col-md-6 segment">
				<label class="" for="flag">By Job / Position</label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer_job', $filters, [], false, [] ), 'id', [ 'name' ], '' );
                
		            wcwh_form_field( 'filter[cjob][]', 
		                [ 'id'=>'cjob', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['cjob'] )? $this->filters['cjob'] : '', $view 
		            ); 
				?>
			</div>

			<div class="col-md-6 segment">
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

			<?php //if( current_user_cans( [ 'save_wh_credit' ] ) ): ?>
			<div class="col-md-6 segment">
				<label class="" for="flag">By Credit Group </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_customer_group', $filters, [], false, [ 'usage'=>1 ] ), 'id', [ 'name' ], '' );
                
		            wcwh_form_field( 'filter[cgroup][]', 
		                [ 'id'=>'cgroup', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['cgroup'] )? $this->filters['cgroup'] : '', $view 
		            ); 
				?>
			</div>
			<?php //endif; ?>
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

	public function column_credit_limit( $item )
	{
		return round_to( $item['credit_limit'], 2, 1, 1 );
	}

	public function column_topup( $item )
	{
		return round_to( $item['topup'], 2, 1, 1 );
	}

	public function column_total_creditable( $item )
	{
		return round_to( $item['total_creditable'], 2, 1, 1 );
	}

	public function column_total_used( $item )
	{
		return round_to( $item['total_used'], 2, 1, 1 );
	}

	public function column_balance( $item )
	{
		return round_to( $item['balance'], 2, 1, 1 );
	}

} //class