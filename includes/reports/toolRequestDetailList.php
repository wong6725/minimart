<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_ToolRequestDetail_Report extends WCWH_Listing 
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
			"docno" 		=> "Document",
			"doc_date"		=> "Date",
			"customer" 		=> "Customer",
			"customer_code"	=> "Customer No.",
			"remark"		=> "Remark",
			"item_group"	=> "Item Group",
			"item_name"		=> "Item Name",
			"uom"			=> "UOM",
			"instalment"	=> "Instalment (Mth)",
			"stock_qty"		=> "Stock Qty",
			"quantity"		=> "Request Qty",
			"fulfil_qty"	=> "Fulfil Qty",
			"fulfil_amt"	=> "Fulfil Amt",
			"bal_qty"		=> "Bal Qty",
			"sprice"		=> "Price (".$currency.")",	
			"sale_amt"		=> "Sale Amt (".$currency.")",	
			"receipt"		=> "Receipt / SO",
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
			'doc_date' => [ 'doc_date', true ],
			'customer' => [ 'customer', true ],
			'item' => [ 'item', true ],
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

	public function print_column_footers( $datas = array(), $all_datas = array() ) 
	{
		if( count( $all_datas ) < $this->args['per_page_row'] ) return;

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_sale = 0;
		$t_fqty = 0;
		$t_famt = 0;
		$t_bal_qty = 0;
		$t_stk = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty += ($data['quantity'])?($data['quantity']):0;
				$t_sale += ($data['sale_amt'])?($data['sale_amt']):0;
				$t_fqty += ($data['fulfil_qty'])?($data['fulfil_qty']):0;
				$t_famt += ($data['fulfil_amt'])?($data['fulfil_amt']):0;
				$t_bal_qty += ($data['bal_qty'])?($data['bal_qty']):0;
				$t_stk += ($data['stock_qty'])?($data['stock_qty']):0;
			}
		}
		// if( $totals )
		// {
		// 	foreach( $totals as $id => $t )
		// 	{
		// 		$t_total+= $t;
		// 	}
		// }

		$colspan = 'no';
		$colnull = ['docno'];
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
			if( $column_key == 'quantity' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'sale_amt' ) $column_display_name = round_to( $t_sale, 2, 1, 1 );
			if( $column_key == 'fulfil_qty' ) $column_display_name = round_to( $t_fqty, 2, 1, 1 );
			if( $column_key == 'fulfil_amt' ) $column_display_name = round_to( $t_famt, 2, 1, 1 );
			if( $column_key == 'bal_qty' ) $column_display_name = round_to( $t_bal_qty, 2, 1, 1 );
			//if( $column_key == 'stock_qty' ) $column_display_name = round_to( $t_stk, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_sale = 0;
		$t_fqty = 0;
		$t_famt = 0;
		$t_bal_qty = 0;
		$t_stk = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty += ($data['quantity'])?($data['quantity']):0;
				$t_sale += ($data['sale_amt'])?($data['sale_amt']):0;
				$t_fqty += ($data['fulfil_qty'])?($data['fulfil_qty']):0;
				$t_famt += ($data['fulfil_amt'])?($data['fulfil_amt']):0;
				$t_bal_qty += ($data['bal_qty'])?($data['bal_qty']):0;
				$t_stk += ($data['stock_qty'])?($data['stock_qty']):0;
			}
		}
		// if( $totals )
		// {
		// 	foreach( $totals as $id => $t )
		// 	{
		// 		$t_total+= $t;
		// 	}
		// }

		$colspan = 'no';
		$colnull = ['docno' ];
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
			if( $column_key == 'quantity' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'sale_amt' ) $column_display_name = round_to( $t_sale, 2, 1, 1 );
			if( $column_key == 'fulfil_qty' ) $column_display_name = round_to( $t_fqty, 2, 1, 1 );
			if( $column_key == 'fulfil_amt' ) $column_display_name = round_to( $t_famt, 2, 1, 1 );
			if( $column_key == 'bal_qty' ) $column_display_name = round_to( $t_bal_qty, 2, 1, 1 );
			//if( $column_key == 'stock_qty' ) $column_display_name = round_to( $t_stk, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
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
				<label class="" for="flag">By Fulfilment</label><br>
				<?php
					$options = [ ''=>'All', 'done'=>'Fulfilled', 'pending'=>'Not Yet' ];
                
		            wcwh_form_field( 'filter[fulfilment]', 
		                [ 'id'=>'fulfilment', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['fulfilment'] )? $this->filters['fulfilment'] : '', $view 
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

			<!--<div class="col-md-3 segment">
				<label class="" for="flag">Item Group</label><br>
				<?php
					/*$grps = $this->refs['setting']['wh_tool_request']['used_item_group'];
					$def = $this->refs['setting']['wh_tool_rpt']['def_item_group'];

					$filters = [ 'id'=>$grps ];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_group', $filters, [], false, [] ), 'id', [ 'code', 'name' ] );
                
					wcwh_form_field( 'filter[item_group]', 
	                    [ 'id'=>'acc_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['item_group'] )? $this->filters['item_group'] : $def, $view 
	                );*/ 
				?>
			</div>-->

			<div class="col-md-6 segment">
				<label class="" for="flag">By Item</label>
				<?php
					$filter = [];
					if( $this->seller ) $filter['seller'] = $this->seller;
					$filter['grp_id'] = $this->setting['wh_tool_request']['used_item_group'];
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
	                wcwh_form_field( 'filter[product_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1 
	                    ], 
	                    isset( $this->filters['product_id'] )? $this->filters['product_id'] : '', $view 
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
	public function column_no( $item ) 
	{
		return $item['no'];
    }

    public function column_docno( $item )
	{
		return $item['docno'];
	}

	public function column_doc_date( $item ) 
	{	
		return $item['doc_date'];  
	}

	public function column_customer( $item )
	{
		return $item['customer'];
	}

	public function column_customer_code( $item )
	{
		return $item['customer_code'];
	}

	public function column_group( $item )
	{
		return $item['group'];
	}

	public function column_item( $item )
	{
		$val = [];
		if( !empty( $item['item'] ) ) $val[] = $item['item'];
		// if( !empty( $item['ítem_code'] ) ) $val[] = $item['ítem_code'];
		
		return implode( ', ', $val );
	}

	public function column_uom( $item )
	{
		return $item['uom'];
	}

	public function column_quantity( $item )
	{
		return round_to( $item['quantity'], 2, 1, 1 );
	}

	public function column_sprice( $item )
	{
		return round_to( $item['sprice'], 2, 1, 1 );
	}

	public function column_sale_amt( $item )
	{
		return round_to( $item['sale_amt'], 2, 1, 1 );
	}

	public function column_stock_qty( $item )
	{
		$clr = "clr-red"; 
		if( $item['stock_qty'] > 0 ) $clr = "clr-green"; 
		return "<span class='{$clr}'>".round_to( $item['stock_qty'], 2, 1, 1 )."</span>";
	}

	public function column_fulfil_qty( $item )
	{
		if( $item['fulfil_qty'] <= 0 ) $clr = "clr-red"; 
		return "<span class='{$clr}'>".round_to( $item['fulfil_qty'], 2, 1, 1 )."</span>";
	}

	public function column_fulfil_amt( $item )
	{
		return round_to( $item['fulfil_amt'], 2, 1, 1 );
	}
	
	public function column_bal_qty( $item )
	{
		if( $item['bal_qty'] > 0 ) $clr = "clr-red"; 
		return "<span class='{$clr}'>".round_to( $item['bal_qty'], 2, 1, 1 )."</span>";
	}
	

} //class