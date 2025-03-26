<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockMoveIn_Report extends WCWH_Listing 
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
			'docno'			=> 'Doc No.',
			"doc_date" 		=> "Doc Date",
			"post_date" 	=> "Post Date",
			"created_at"	=> "Create Date",
			"ref_doc"		=> "Ref Doc",
			"doc_type"		=> "Doc Type",
			"dn"			=> "DN",
			"supplier"		=> "Supplier",
			"remark"		=> "Remark",
			"category"		=> "Category",
			"product"		=> "Product",
			"uom"			=> "UOM",
			"qty"			=> "Qty",
			"metric"		=> "Metric (kg/l)",
			"uprice"		=> "Price",
			"total_price"	=> "Total Price",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'remark', 'metric' );

		if( current_user_cans( ['hide_amt_movement_wh_reports'] ) )
		{
			$col[] = 'uprice';
			$col[] = 'total_price';
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
			'prdt_code' => [ 'prdt_code', true ],
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
			<div class="segment col-md-3">
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

			<div class="segment col-md-3">
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

			<div class="segment col-md-3">
				<label class="" for="flag">By Doc Type </label><br>
				<?php
					$options = array_merge( [''=>'All'], ['good_receive'=>'Goods Receipt', 'reprocess'=>'Reprocess', 'transfer_item'=>'Transfer Item', 'do_revise'=>'DO Revise', 'pos_transactions'=>'POS In',] );
	                wcwh_form_field( 'filter[doc_type]', 
	                    [ 'id'=>'doc_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options, 
	                    ], 
	                    isset( $this->filters['doc_type'] )? $this->filters['doc_type'] : '', $view 
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
		</div>

		<div class="row">
			<div class="segment col-md-6">
				<label class="" for="flag">By Supplier </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_supplier', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'name', 'status_name' ] );
                
		            wcwh_form_field( 'filter[supplier][]', 
		                [ 'id'=>'supplier', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['supplier'] )? $this->filters['supplier'] : '', $view 
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

			<?php
				if( $this->seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [] );
					
				if( $wh && $wh['parent'] > 0 ):
			?>
			<div class="segment col-md-4">
				<label class="" for="flag">Follow DC Document Date </label><br>
				<?php
			        wcwh_form_field( 'filter[follow_dc]', 
			            [ 'id'=>'', 'type'=>'checkbox', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
			            isset( $this->filters['follow_dc'] )? $this->filters['follow_dc'] : '', $view 
			        ); 
				?>
			</div>
			<?php endif; ?>
			
			<div class="segment col-md-6">
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

			<div class="segment col-md-6">
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
		$t_line_total = 0;

		$t_in_qty = 0;
		$t_in_amount = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['metric'] )? $data['metric'] : 0;
				$t_line_total+= ( $data['total_price'] )? $data['total_price'] : 0;
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
			if( $column_key == 'total_price' ) $column_display_name = round_to( $t_line_total, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_unit = 0;
		$t_line_total = 0;

		$t_in_qty = 0;
		$t_in_amount = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty+= ( $data['qty'] )? $data['qty'] : 0;
				$t_unit+= ( $data['metric'] )? $data['metric'] : 0;
				$t_line_total+= ( $data['total_price'] )? $data['total_price'] : 0;
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
			if( $column_key == 'no' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'qty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'metric' ) $column_display_name = round_to( $t_unit, 2, 1, 1 );
			if( $column_key == 'total_price' ) $column_display_name = round_to( $t_line_total, 2, 1, 1 );

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

	public function column_supplier( $item )
	{
		$html = [];
		if( $item['supplier_code'] ) $html[] = $item['supplier_code'];
		if( $item['supplier_name'] ) $html[] = $item['supplier_name'];

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
	
	public function column_uprice( $item )
	{
		return ( $item['uprice'] != 0 )? round_to( $item['uprice'], 5, 1, 1 ) : '';
	}
	
	public function column_total_price( $item )
	{
		return ( $item['total_price'] != 0 )? round_to( $item['total_price'], 2, 1, 1 ) : '';
	}
	
} //class