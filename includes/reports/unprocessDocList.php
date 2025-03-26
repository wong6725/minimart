<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_UnprocessedDoc_Report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $DocType = array();

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
		$cols = [
			'no'			=> '',
			'docno'			=> 'Doc',
			"doc_date" 		=> "Date",
			"doc_type"		=> "Doc Type",
			"issue_type"	=> "Issue Type",
			"supplier"		=> "Supplier",
			"client"		=> "Client",
			"doc_status"	=> "Doc Status",
			"movement_type"	=> "In/Out",
			"ref_doc"		=> "Ref Doc",
			"ref_doc_type"	=> "Ref Doc Type",
			"category"		=> "Category",
			"item"			=> "Item",
			"uom"			=> "UOM",
			"bqty"			=> "Qty",
			"uqty"			=> "Uqty",
			"in_price"		=> "In Price",
			"in_amt"		=> "In Amt",
			"sell_price"	=> "Sell Price",
			"sell_amt"		=> "Sell Amt",
			//"cost_price"	=> "Cost Price",
			//"cost_amt"		=> "Cost Amt",
		];

		$filters = $this->filters;
		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array( 'uqty' );

		if( current_user_cans( ['hide_amt_unprocessed_doc_wh_reports'] ) )
		{
			$col[] = 'in_price';
			$col[] = 'in_amt';
			$col[] = 'sell_price';
			$col[] = 'sell_amt';
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'docno' 			=> [ 'docno', true ],
			'doc_date' 			=> [ 'doc_date', true ],
			'category'			=> [ 'category_code', true ],
			'item'				=> [ 'item_code', true ],
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
					$options = array_merge( [''=>'All'], $this->DocType );
	                wcwh_form_field( 'filter[doc_type]', 
	                    [ 'id'=>'doc_type', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options, 
	                    ], 
	                    isset( $this->filters['doc_type'] )? $this->filters['doc_type'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">By Supplier </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_supplier', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'name', 'status_name' ], '' );
					
	                wcwh_form_field( 'filter[supplier][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['supplier'] )? $this->filters['supplier'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">By Client </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;
					$options = options_data( apply_filters( 'wcwh_get_client', $filters, [], false, ['usage'=>0] ), 'id', [ 'code', 'name', 'status_name' ], '' );
					
	                wcwh_form_field( 'filter[client][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['client'] )? $this->filters['client'] : '', $view 
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

	public function column_category( $item )
	{
		$html = [];
		if( $item['category_code'] ) $html[] = $item['category_code'];
		if( $item['category_name'] ) $html[] = $item['category_name'];

		return implode( ' - ', $html );
	}

	public function column_item( $item )
	{
		$html = [];
		if( $item['item_code'] ) $html[] = $item['item_code'];
		if( $item['item_name'] ) $html[] = $item['item_name'];

		return implode( ' - ', $html );
	}

	public function column_bqty( $item )
	{
		return ( $item['bqty'] != 0 )? round_to( $item['bqty'], 2, 1, 1 ) : '';
	}

	public function column_in_price( $item )
	{
		return ( $item['in_price'] != 0 )? round_to( $item['in_price'], 5, 1, 1 ) : '';
	}

	public function column_in_amt( $item )
	{
		return ( $item['in_amt'] != 0 )? round_to( $item['in_amt'], 2, 1, 1 ) : '';
	}

	public function column_sell_price( $item )
	{
		return ( $item['sell_price'] != 0 )? round_to( $item['sell_price'], 2, 1, 1 ) : '';
	}

	public function column_sell_amt( $item )
	{
		return ( $item['sell_amt'] != 0 )? round_to( $item['sell_amt'], 2, 1, 1 ) : '';
	}
	
} //class