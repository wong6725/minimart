<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_PO_ePayment_report extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;
	public $doc_opts = [];

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
			"po_no"			=> "PO No",
			"po_date"		=> "PO Date",
			"supplier"		=> "Supplier",
			"invoice_no"	=> "Invoice No",
			"invoice_date"	=> "Invoice Date",
			"ref_po_no"		=> "Ref. PO No",
			"gr_date"		=> "GR Date",
			"category"		=> "Category",
			"amount"		=> "Amount",
			"dn_amt"		=> "DN Amt",
			"cn_amt"		=> "CN Amt",
			"final_amt"		=> "Final Amount",
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
			'supplier' => [ 'supplier_code', true ],
			'po_no' => [ 'po_no', true ],
			'po_date' => [ 'po_date', true ],
			'category' => [ 'category_code', true ],
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
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
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
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $to_date )? $to_date : '', $view 
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
				<label class="" for="flag">By Doc No. </label><br>
				<?php
					$options = $this->doc_opts;

	                wcwh_form_field( 'filter[doc_id][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    isset( $this->filters['doc_id'] )? $this->filters['doc_id'] : '', $view 
                ); 
				?>
			</div>

			<div class="col-md-6 segment">
				<label class="" for="flag">Paste PO No. from Excel </label><br>
				<?php
					$options = [];
					if( !empty( $this->filters['sequence_doc'] ) )
					{
						foreach( $this->filters['sequence_doc'] as $opt )
						{
							$options[ $opt ] = $opt;
						}
					}
	                wcwh_form_field( 'filter[sequence_doc][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Tag','select2Empty'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    isset( $this->filters['sequence_doc'] )? $this->filters['sequence_doc'] : '', $view 
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
		
		$t_amt = 0;
		$t_f = 0;
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$t_amt+= ( $data['amount'] )? $data['amount'] : 0;
				$t_f+= ( $data['final_amt'] )? $data['final_amt'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'supplier', 'po_no', 'po_date', 'invoice_no' ];
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
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'final_amt' ) $column_display_name = round_to( $t_f, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$t_amt = 0;
		$t_f = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$t_amt+= ( $data['amount'] )? $data['amount'] : 0;
				$t_f+= ( $data['final_amt'] )? $data['final_amt'] : 0;
			}
		}

		$colspan = 'no';
		$colnull = [ 'supplier', 'po_no', 'po_date', 'invoice_no' ];
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
			if( $column_key == 'amount' ) $column_display_name = round_to( $t_amt, 2, 1, 1 );
			if( $column_key == 'final_amt' ) $column_display_name = round_to( $t_f, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$h_match = $this->get_header_match();
		if( $item[ $h_match ] != $this->prev_match )
			$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['doc_id'], $item['id'] );
		
		return $html;
    }

	public function column_no( $item ) 
	{
		return $item['no'];
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
		if( $item['category_name'] ) $html[] = $item['category_name'];

		return implode( ' - ', $html );
    }

    public function column_dn_amt( $item )
	{
		return round_to( $item['dn_amt'], 2, 1, 1 );
	}

	public function column_cn_amt( $item )
	{
		return round_to( $item['cn_amt'], 2, 1, 1 );
	}

	public function column_amount( $item )
	{
		return round_to( $item['amount'], 2, 1, 1 );
	}

	public function column_final_amt( $item )
	{
		return round_to( $item['final_amt'], 2, 1, 1 );
	}
	
} //class