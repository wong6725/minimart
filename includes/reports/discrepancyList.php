<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Discrepancy_Report extends WCWH_Listing 
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
		//$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		$cols = array(
			'no'			=> '',
			"docno"			=> "DO No.",
			"doc_date"		=> "Doc Date",
			"created_at"	=> "Create Date",
			"client"		=> "Client",
			"sync_status"	=> "Sync Stat",
			"product"		=> "Product",
			"category"		=> "Category",
			"bqty"			=> "Qty",
			"uom"			=> "UOM",
			"uqty"			=> "Received Qty",
			"remain_qty"	=> "Remain Qty",
			"unpost_qty"	=> "Not Post Qty",
			"sprice"		=> "Price",
			"selling_amt"	=> "Amount",
		);

		$filters = $this->filters;

		return $cols;
	}

	public function get_header_cols()
	{
		return array(
			"docno",
			"doc_date",
			"created_at",
			"client",
		);
	}

	public function get_header_match()
	{
		return 'doc_id';
	}

	public function get_hidden_column()
	{
		$col = [];

		if( current_user_cans( ['hide_amt_discrepancy_wh_reports'] ) )
		{
			$col[] = 'sprice';
			$col[] = 'selling_amt';
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [
			'docno' => [ 'docno', true ],
			'doc_date' => [ 'doc_date', true ],
			'created_at' => [ 'created_at', true ],
			'client' => [ 'client_code', true ],
			'product' => [ 'item_code', true ],
			'category' => [ 'category_code', true ],
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
				<label class="" for="flag">By Sync Status </label><br>
				<?php
					$options = [ ''=>'All', 'yes'=>'Synced', 'no'=>'Not Sync' ];
					
	                wcwh_form_field( 'filter[sync]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options,
	                    ], 
	                    isset( $this->filters['sync'] )? $this->filters['sync'] : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-3 segment">
				<label class="" for="flag">By Client </label><br>
				<?php
					$filters = [];
					if( $this->seller ) $filters['seller'] = $this->seller;

					$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );

					$opts = [];
					if( ! $wh['parent'] )
					{
						if( $wh ) $outlets = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$wh['id'] ], [], false, [ 'meta'=>['client_company_code'] ] );

						if( $outlets )
						{
							$filters['code'] = [];
							foreach( $outlets as $outlet )
							{
								if( !empty( $outlet['client_company_code'] ) )
								$filters['code'] = array_merge( $filters['code'], json_decode( stripslashes( $outlet['client_company_code'] ), true ) );
							}

							$filters['code'] = array_unique( $filters['code'] );
							$opts = apply_filters( 'wcwh_get_client', $filters, [], false, [] );
						}
					}
					else if( $filters['seller'] != $wh['id'] )
					{
						$outlet = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['client_company_code'] ] );

						$filters['code'] = json_decode( stripslashes( $outlet['client_company_code'] ), true );
						$opts = apply_filters( 'wcwh_get_client', $filters, [], false, [] );
					}
					else
					{
						$opts = apply_filters( 'wcwh_get_client', $filters, [], false, [] );
					}

					$options = options_data( $opts, 'code', [ 'code', 'name' ], '' );
					
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
					$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					
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
		$t_receive = 0;
		$t_remain = 0;
		$t_unpost = 0;
		$t_amt = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_qty+= ( $data['bqty'] )? $data['bqty'] : 0;
				$t_receive+= ( $data['uqty'] )? $data['uqty'] : 0;
				$t_remain+= ( $data['remain_qty'] )? $data['remain_qty'] : 0;
				$t_unpost+= ( $data['unpost_qty'] )? $data['unpost_qty'] : 0;
				$t_amt+= ( $data['selling_amt'] )? $data['selling_amt'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'docno' ];
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
			if( $column_key == 'bqty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'uqty' ) $column_display_name = round_to( $t_receive, 2, 1, 1 );
			if( $column_key == 'remain_qty' ) $column_display_name = round_to( $t_remain, 2, 1, 1 );
			if( $column_key == 'unpost_qty' ) $column_display_name = round_to( $t_unpost, 2, 1, 1 );
			if( $column_key == 'selling_amt' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_qty = 0;
		$t_receive = 0;
		$t_remain = 0;
		$t_unpost = 0;
		$t_amt = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_qty+= ( $data['bqty'] )? $data['bqty'] : 0;
				$t_receive+= ( $data['uqty'] )? $data['uqty'] : 0;
				$t_remain+= ( $data['remain_qty'] )? $data['remain_qty'] : 0;
				$t_unpost+= ( $data['unpost_qty'] )? $data['unpost_qty'] : 0;
				$t_amt+= ( $data['selling_amt'] )? $data['selling_amt'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'docno' ];
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
			if( $column_key == 'bqty' ) $column_display_name = round_to( $t_qty, 2, 1, 1 );
			if( $column_key == 'uqty' ) $column_display_name = round_to( $t_receive, 2, 1, 1 );
			if( $column_key == 'remain_qty' ) $column_display_name = round_to( $t_remain, 2, 1, 1 );
			if( $column_key == 'unpost_qty' ) $column_display_name = round_to( $t_unpost, 2, 1, 1 );
			if( $column_key == 'selling_amt' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );

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
		if( $item['item_code'] ) $html[] = $item['item_code'];
		if( $item['item_name'] ) $html[] = $item['item_name'];

		return implode( ' - ', $html );
    }
	
	public function column_bqty( $item )
	{
		return ( $item['bqty'] )? round_to( $item['bqty'], 2, 1, 1 ) : '';
	}
	
	public function column_uqty( $item )
	{
		return ( $item['uqty'] )? round_to( $item['uqty'], 2, 1, 1 ) : '';
	}

	public function column_remain_qty( $item )
	{
		return ( $item['remain_qty'] )? round_to( $item['remain_qty'], 2, 1, 1 ) : '';
	}

	public function column_unpost_qty( $item )
	{
		return ( $item['unpost_qty'] )? round_to( $item['unpost_qty'], 2, 1, 1 ) : '';
	}

	public function column_sprice( $item )
	{
		return ( $item['sprice'] )? round_to( $item['sprice'], 2, 1, 1 ) : '';
	}
	
	public function column_selling_amt( $item )
	{
		return ( $item['selling_amt'] )? round_to( $item['selling_amt'], 2, 1, 1 ) : '';
	}
	
} //class