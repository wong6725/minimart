<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_CustomerTool_Report extends WCWH_Listing 
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
			"name" 			=> "Name",
			"code"		    => "Cust No",
			"serial"		=> "Cust No",
			"uid" 			=> "SAP Emp ID",
			"acc_code"		=> "Acc Type",
			"wage_type"		=> "Wage Type",
			"date"			=> "Date",
			"lpurchase_at"	=> "Purchase Date",
			"credit_used"	=> "Credit ({$currency})",
		);
	}

	public function get_hidden_column()
	{
		$col = [ 'serial' ];

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'name' => [ 'name', true ],
			'code' => [ 'code', true ],
			'serial' => [ 'serial', true ],
			'uid' => [ 'uid', true ],
			'date' => [ 'date', true ],
			'lpurchase_at' => [ 'lpurchase_at', true ],
			'credit_used' => [ 'credit_used', true ],
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
			
			<div class="col-md-3 segment">
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

			<div class="col-md-3 segment">
				<label class="" for="flag">Item Group</label><br>
				<?php
					$grps = $this->refs['setting']['wh_tool_request']['used_item_group'];
					$def = $this->refs['setting']['wh_tool_rpt']['def_item_group'];

					$filters = [ 'id'=>$grps ];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_group', $filters, [], false, [] ), 'id', [ 'code', 'name' ], '' );
                
					wcwh_form_field( 'filter[item_group]', 
	                    [ 'id'=>'acc_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options, 
	                    ], 
	                    isset( $this->filters['item_group'] )? $this->filters['item_group'] : $def, $view 
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
				if( current_user_cans( [ 'view_credit_detail_wh_reports' ] ) )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'" data-form="edit-customer_tool_report"
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
		
		$t_creditable = 0;
		$t_credit = 0;
		$t_balance = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_creditable+= $data['çreditable'];
				$t_credit+= $data['credit_used'];
			}
		}
		$t_balance = $t_creditable - $t_credit;

		$colspan = 'no';
		$colnull = [ 'name', 'code', 'uid', 'customer_code', 'uid' ];
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
			if( $column_key == 'credit_used' ) $column_display_name = round_to( $t_credit, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_creditable = 0;
		$t_credit = 0;
		$t_balance = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_creditable+= $data['çreditable'];
				$t_credit+= $data['credit_used'];
			}
		}
		$t_balance = $t_creditable - $t_credit;

		$colspan = 'no';
		$colnull = [ 'name', 'code', 'uid' ];
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
			if( $column_key == 'credit_used' ) $column_display_name = round_to( $t_credit, 2, 1, 1 );

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

	public function column_name( $item ) 
	{	
		$actions = [];
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_credit_used( $item )
	{
		return round_to( $item['credit_used'], 2, 1, 1 );
	}
	
} //class