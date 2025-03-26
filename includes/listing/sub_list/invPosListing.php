<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_InvPOS_List extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $seller = 0;

	public $date_format;
	public $converse = [];
	public $i = 0;

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
			"date"			=> "Date",
			"qty"			=> "Qty",
			"weight"		=> "Metric (kg/l)",
			"avg_weight"	=> "Avg Metric",
			"avg_price"		=> "Avg Price",
			"line_total"	=> "Amount",
		);

		return $cols;
	}

	public function get_hidden_column()
	{
		$col = array();

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
		
		$qty = 0;
		$weight = 0;
		$amt = 0;
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				$qty+= ( $data['qty'] )? $data['qty'] : 0;
				$weight+= ( $data['weight'] )? $data['weight'] : 0;
				$amt+= ( $data['line_total'] )? $data['line_total'] : 0;
			}
		}

		$colspan = 'date';
		$colnull = [];
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
			if( $column_key == 'date' ) $column_display_name = 'TOTAL:';
			if( $column_key == 'qty' ) $column_display_name = round_to( $qty, 2, 1, 1 );
			if( $column_key == 'weight' ) $column_display_name = round_to( $weight, 3, 1, 1 );
			if( $column_key == 'line_total' ) $column_display_name = round_to( $amt, 2, 1, 1 );
			if( $column_key == 'avg_price' ) $column_display_name = round_to( $amt / $qty, 5, 1, 1 );
			if( $column_key == 'avg_weight' ) $column_display_name = round_to( $weight / $qty, 3, 1, 1 );

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
		return current_user_cans( ['wh_admin_support'] )? "<span title='{$item['id']}'>".($i).".</span>" : ($i).".";;
    }
	
	public function column_qty( $item )
	{
		return ( $item['qty'] )? round_to( $item['qty'], 2, 1, 1 ) : '';
	}

	public function column_weight( $item )
	{
		return ( $item['weight'] )? round_to( $item['weight'], 3, 1, 1 ) : '';
	}

	public function column_avg_weight( $item )
	{
		return ( $item['avg_weight'] )? round_to( $item['avg_weight'], 3, 1, 1 ) : '';
	}
	
	public function column_avg_price( $item )
	{
		return ( $item['avg_price'] > 0 )? round_to( $item['avg_price'], 5, 1, 1 ) : '';
	}
	
	public function column_line_total( $item )
	{
		return ( $item['line_total'] > 0 )? round_to( $item['line_total'], 2, 1, 1 ) : '';
	}
	
} //class