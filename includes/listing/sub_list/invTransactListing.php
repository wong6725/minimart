<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_InvTransact_List extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $date_format;
	public $converse = [];
	public $i = 0;

	private $bal_qty = 0;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->date_format = get_option( 'date_format' );
	}

	public function set_refs()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
	}
	
	public function get_columns() 
	{
		$cols = array(
			'num'			=> '',
			"docno"			=> "Doc No.",
			"doc_post_date"	=> "Date",
			"name"			=> "Ref",
			"from_item"		=> "From Item",
			"from_qty"		=> "From Qty",
			"plus_sign"		=> "Transact",
			"bqty"			=> "Qty",
			"bal_qty"		=> "Bal Qty",
			"bunit"			=> "Metric (kg/l)",
			"unit_price"	=> "Price",
			"total_price"	=> "Total Price",
			"unit_cost"		=> "Cost",
			"total_cost"	=> "Total Cost",
		);

		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array();

		if( ! current_user_cans( ['wh_admin_support', 'view_amount_wh_inventory'] ) )
		{
			$col = [ 'unit_cost', 'total_cost', 'unit_price', 'total_price' ];
		}
		if( ! current_user_cans( ['wh_admin_support'] ) )
		{
			$col[] = 'bal_qty';
		}
		if( ! current_user_cans( ['wh_admin_support'] ) )
			$col[] = 'bunit';

		return $col;
	}

	public function get_sortable_columns()
	{
		$cols = [];

		return $cols;
	}

	public function get_bulk_actions() 
	{
		$actions = array();
		
		return $actions;
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		$this->prepare_items();
		$this->display();
	}

	public function get_status_action( $item )
	{
		return array();
	}
	
	public function _filter_search(){}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		$qty_in = 0;
		$qty_out = 0;
		$unit_in = 0;
		$unit_out = 0;
		$t_total_price = 0;
		$t_total_cost = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$qty_in+= ( $data['plus_sign'] == '+' && $data['bqty'] )? $data['bqty'] : 0;
				$qty_out+= ( $data['plus_sign'] == '-' && $data['bqty'] )? $data['bqty'] : 0;
				$unit_in+= ( $data['plus_sign'] == '+' && $data['bunit'] )? $data['bunit'] : 0;
				$unit_out+= ( $data['plus_sign'] == '-' && $data['bunit'] )? $data['bunit'] : 0;
				$t_total_price+= ( $data['total_price'] )? $data['total_price'] : 0;
				$t_total_cost+= ( $data['total_cost'] )? $data['total_cost'] : 0;
			}
		}

		$colspan = 'docno';
		$colnull = [ 'doc_post_date' ];
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
			if( $column_key == 'docno' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'bqty' ) $column_display_name = round_to( $qty_in - $qty_out, 2, 1, 1 );
			if( $column_key == 'bunit' ) $column_display_name = round_to( $unit_in - $unit_out, 2, 1, 1 );
			if( $column_key == 'unit_price' ) $column_display_name = round_to( $t_total_price / $qty_in, 5, 1, 1 );
			if( $column_key == 'total_price' ) $column_display_name = round_to( $t_total_price, 2, 1, 1 );
			if( $column_key == 'unit_cost' ) $column_display_name = round_to( $t_total_cost / $qty_out, 5, 1, 1 );
			if( $column_key == 'total_cost' ) $column_display_name = round_to( $t_total_cost, 2, 1, 1 );

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
    public function column_num( $item ) 
	{	
		$this->i++; $i = $this->i;
		return current_user_cans( ['wh_admin_support'] )? "<span title='{$item['hid']} - {$item['did']}'>".($i).".</span>" : ($i).".";;
    }

    public function column_docno( $item )
    {
    	$html = $item['docno'];

    	if( $item['doc_id'] && $item['doc_type'] )
		{	
			$section = '';
			switch( $item['doc_type'] )
			{
				case 'good_receive':
					$section = 'wh_good_receive';
				break;
				case 'reprocess':
					$section = 'wh_reprocess';
				break;
				case 'transfer_item':
					$section = 'wh_transfer_item';
				break;
				case 'good_issue':
					$section = 'wh_good_issue';
				break;
				case 'delivery_order':
					$section = 'wh_delivery_order';
				break;
				case 'block_stock':
					$section = 'wh_block_stock';
				break;
				case 'block_action':
					$section = 'wh_block_action';
				break;
				case 'good_return':
					$section = 'wh_good_return';
				break;
				case 'do_revise':
					$section = 'wh_do_revise';
				break;
				case 'stock_adjust':
					$section = 'wh_stock_adjust';
				break;
			}

			if( $section )
			{
				$args = [ 'id'=>$item['doc_id'], 'service'=>$section.'_action', 'title'=>$html, 'permission'=>[ 'access_'.$section ] ];
				$html = $this->get_external_btn( $html, $args );
			}
		}
		return $html;
    }

    public function column_doc_post_date( $item ) 
	{
		return date_i18n( $this->date_format, strtotime( $item['doc_post_date'] ) );
    }

    public function column_plus_sign( $item )
    {
    	return ( $item['plus_sign'] == '+' )? 'In +' : ( ( $item['plus_sign'] == '-' )? 'Out -' : '' );
    }

    public function column_name( $item )
    {
    	if( !empty( trim( $item['supplier'] ) ) ) return $item['supplier'];
    	if( !empty( trim( $item['client'] ) ) ) return $item['client'];
    }

    public function column_from_item( $item ) 
	{
		$html = [];
		if( $item['ccode'] ) $html[] = $item['ccode'];
		if( $item['cuom'] ) $html[] = $item['cuom'];

		return implode( ' - ', $html );
    }

    public function column_from_qty( $item ) 
	{
		if( $item['from_qty'] && $item['conversion_rate'] )
		{
			$html = [ $item['from_qty'] ];
			$html[] = $item['conversion_rate'];	
		}	

		return implode( ' x ', $html );
    }
	
	public function column_bqty( $item )
	{
		return ( $item['bqty'] )? round_to( $item['bqty'], 2, 1, 1 ) : '';
	}

	public function column_bal_qty( $item )
	{
		$this->bal_qty+= ( $item['plus_sign'] == '+' )? $item['bqty'] : $item['bqty'] * (-1);
		return round_to( $this->bal_qty, 2, 1, 1, 1 );
	}

	public function column_bunit( $item )
	{
		return ( $item['bunit'] )? round_to( $item['bunit'], 3, 1, 1 ) : '';
	}
	
	public function column_unit_price( $item )
	{
		return ( $item['unit_price'] > 0 )? round_to( $item['unit_price'], 5, 1, 1 ) : '';
	}
	
	public function column_total_price( $item )
	{
		return ( $item['total_price'] > 0 )? round_to( $item['total_price'], 2, 1, 1 ) : '';
	}
	
	public function column_unit_cost( $item )
	{
		return ( $item['unit_cost'] > 0 )? round_to( $item['unit_cost'], 5, 1, 1 ) : '';
	}

	public function column_total_cost( $item )
	{
		return ( $item['total_cost'] > 0 )? round_to( $item['total_cost'], 2, 1, 1 ) : '';
	}
	
} //class