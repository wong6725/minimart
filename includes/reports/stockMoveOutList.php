<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockMoveOut_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $GIType = array();

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
			'docno'			=> 'Doc No.',
			"doc_date" 		=> "Doc Date",
			"post_date" 	=> "Post Date",
			"created_at"	=> "Create Date",
			"issue_type"	=> "Issue Type",
			"ref_doc"		=> "Ref Doc",
			"ref_doc_type"	=> "Ref Type",
			"link_doc"		=> "Link Doc",
			"client"		=> "Client",
			"remark"		=> "Remark",
			"category"		=> "Category",
			"product"		=> "Product",
			"uom"			=> "UOM",
			"qty"			=> "Qty",
			"metric"		=> "Metric (kg/l)",
			"ucost"			=> "Cost",
			"total_cost"	=> "Total Cost",
			"sprice"		=> "SPrice",
			"amount"		=> "Amount",
			"profit"		=> "Profit",
			"adj_total_sale"=> "Adj Sale",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'remark', 'metric' );

		if( current_user_cans( ['hide_amt_movement_wh_reports'] ) )
		{
			$col[] = 'ucost';
			$col[] = 'total_cost';
			$col[] = 'sprice';
			$col[] = 'amount';
			$col[] = 'profit';
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'docno' => [ 'docno', true ],
			'doc_date' => [ 'doc_date', true ],
			'post_date' => [ 'post_date', true ],
			'created_at' => [ 'created_at', true ],
			'ref_doc' => [ 'ref_doc', true ],
			'product' => [ 'prdt_code', true ],
			'qty' => [ 'qty', true ],
			'metric' => [ 'metric', true ],
			'total_cost' => [ 'total_cost', true ],
			'amount' => [ 'amount', true ],
			'profit' => [ 'profit', true ],
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
				<label class="" for="flag">By Document Type </label><br>
				<?php
					$options = array_merge( [''=>'All'], $this->GIType );
	                wcwh_form_field( 'filter[good_issue_type]', 
	                    [ 'id'=>'good_issue_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options, 
	                    ], 
	                    isset( $this->filters['good_issue_type'] )? $this->filters['good_issue_type'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-3 segment">
				<label class="" for="flag">Document Date By</label><br>
				<?php
					$options = [ 'post_date'=>'Posting Date', 'doc_date'=>'Document Date' ];
					wcwh_form_field( 'filter[date_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'class'=>[], 'attrs'=>[],
	                    	'options'=>$options,
	                    ], 
	                   isset( $this->filters['date_type'] )? $this->filters['date_type'] : '', $view 
	                ); 
				?>
			</div>
			
			<div class="col-md-6 segment">
				<label class="" for="flag">By Client </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_client', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'name', 'status_name' ] );
                
		            wcwh_form_field( 'filter[client][]', 
		                [ 'id'=>'client', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
		            ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">By Item Group </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_group', $filters, [], false, [] ), 'id', [ 'code', 'name' ], '' );
					
	                wcwh_form_field( 'filter[group][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['group'] )? $this->filters['group'] : '', $view 
	                ); 
				?>
			</div>
			
			<div class="col-md-6 segment">
				<label class="" for="flag">By Category </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_item_category', $filters, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
					
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">By Item </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;

					if( current_user_cans( [ 'item_visible_wh_reports' ] ) )
					{
						$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					}
					else
					{
						$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'status_name' ], '' );
					}
					
	                wcwh_form_field( 'filter[product][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['product'] )? $this->filters['product'] : '', $view 
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
		
		$t_qty = 0;
		$t_unit = 0;
		$t_amount = 0;
		$t_total_cost = 0;
		$t_profit = 0;
		$t_adj = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['metric'] )? $data['metric'] : 0;
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
				$t_total_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
				$t_profit+= ( $data['profit'] )? $data['profit'] : 0;
				$t_adj+= ( $data['adj_total_sale'] )? $data['adj_total_sale'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'docno', 'doc_date' ];
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
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'metric' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amount, 2, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_total_cost, 2, 1, 1 );
			if( $column_key == 'profit' ) $column_display_name = round_to( $t_profit, 2, 1, 1 );
			if( $column_key == 'adj_total_sale' ) $column_display_name = round_to( $t_adj, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_unit = 0;
		$t_amount = 0;
		$t_total_cost = 0;
		$t_profit = 0;
		$t_adj = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['metric'] )? $data['metric'] : 0;
				$t_amount+= ( $data['amount'] )? $data['amount'] : 0;
				$t_total_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
				$t_profit+= ( $data['profit'] )? $data['profit'] : 0;
				$t_adj+= ( $data['adj_total_sale'] )? $data['adj_total_sale'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'docno', 'doc_date' ];
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
			if( $column_key == 'no' ) $column_display_name = 'Total:';
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'metric' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amount, 2, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_total_cost, 2, 1, 1 );
			if( $column_key == 'profit' ) $column_display_name = round_to( $t_profit, 2, 1, 1 );
			if( $column_key == 'adj_total_sale' ) $column_display_name = round_to( $t_adj, 2, 1, 1 );

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

	public function column_docno( $item ) 
	{	
		return sprintf( '%1$s', '<strong>'.$item['docno'].'</strong>' );  
	}
	
	public function column_client( $item )
	{
		$html = [];
		if( $item['client_code'] ) $html[] = $item['client_code'];
		if( $item['client_name'] ) $html[] = $item['client_name'];

		return implode( ' - ', $html );
	}

	public function column_category( $item )
	{
		$html = [];
		if( $item['category_code'] ) $html[] = $item['category_code'];
		if( $item['category'] ) $html[] = $item['category'];

		return implode( ' - ', $html );
	}

	public function column_product( $item )
	{
		$html = [];
		if( $item['prdt_code'] ) $html[] = $item['prdt_code'];
		if( $item['prdt_name'] ) $html[] = $item['prdt_name'];

		return implode( ' - ', $html );
	}
	
	public function column_qty( $item )
	{
		return ( $item['qty'] != 0 )? round_to( $item['qty'], 2, 1, 1 ) : '';
	}

	public function column_metric( $item )
	{
		return ( $item['metric'] != 0 )? round_to( $item['metric'], 3, 1, 1 ) : '';
	}
	
	public function column_ucost( $item )
	{
		return ( $item['ucost'] != 0 )? round_to( $item['ucost'], 5, 1, 1 ) : '';
	}

	public function column_total_cost( $item )
	{
		return ( $item['total_cost'] != 0 )? round_to( $item['total_cost'], 2, 1, 1 ) : '';
	}

	public function column_sprice( $item )
	{
		return ( $item['sprice'] != 0 )? round_to( $item['sprice'], 5, 1, 1 ) : '';
	}
	
	public function column_amount( $item )
	{
		return ( $item['amount'] != 0 )? round_to( $item['amount'], 2, 1, 1 ) : '';
	}

	public function column_profit( $item )
	{
		return ( $item['profit'] != 0 )? round_to( $item['profit'], 2, 1, 1 ) : '';
	}
	
} //class