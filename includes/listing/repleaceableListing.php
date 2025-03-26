<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Repleaceable_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();
	public $hidden_col = [];

	protected $section_id = "wh_repleaceable";

	public $seller = 0;

	protected $canSupport = false;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		if( current_user_cans( ['wh_support', 'wh_admin_support'] ) )
			$this->canSupport = true;
	}

	public function set_refs()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}
	
	public function get_columns() 
	{
		return array(
			//'cb'			=> '<input type="checkbox" />',
			"name" 			=> "Name",
			"_uom_code"		=> "UOM",
			"serial"		=> "Barcode",
			"item_group"	=> "Group",
			"category"		=> "Category",
			"store_type"	=> "Storing",
			"ref_by"		=> "Reference By",
			"ret_by"		=> "Returnable Ref",
			"total_in"		=> "IN+",
			"total_out"		=> "OUT-",
			"qty"			=> "Stocks",
			"allocated_qty"	=> "POS Sales",
			//"total_sales_qty" => "Sales",
			"balance"		=> "Balance",
		);
	}

	public function get_hidden_column()
	{
		$hidden = $this->hidden_col;

		return $hidden;
	}

	public function get_sortable_columns()
	{
		return array();
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

		//$this->search_box( 'Search', "s" );
		$this->prepare_items();
		$this->display();
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<?php
				$filter = [ 'wh_code'=>$this->warehouse['code'] ];
				if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
				$options = options_data( apply_filters( 'wcwh_get_storage', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );

				if( $options ):
			?>
				<div class="segment col-md-4">
					<label class="" for="flag">Inventory Type <sup>Current: <?php echo $options[$this->filters['strg_id']]; ?></sup></label>
					<?php
						wcwh_form_field( 'filter[strg_id]', 
	                    	[ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        	'options'=> $options, 'offClass'=>true
	                    	], 
	                    	isset( $this->filters['strg_id'] )? $this->filters['strg_id'] : '', $view 
	                	); 
					?>
				</div>
			<?php endif; ?>
			<div class="segment col-md-8">
				<label class="" for="flag">By Items</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', '_sku', '_uom_code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[item_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['item_id'] )? $this->filters['item_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Category</label><br>
				<?php
					$filter = [];
                	if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
                	$options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'term_id', [ 'slug', 'name' ], '' );
	                
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'category', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Group</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_item_group', $filter, [], false, [] ), 'id', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[grp_id][]', 
	                    [ 'id'=>'grp_id', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['grp_id'] )? $this->filters['grp_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Brand</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_brand', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
		            wcwh_form_field( 'filter[_brand][]', 
		                [ 'id'=>'_brand', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['_brand'] )? $this->filters['_brand'] : '', $view 
		            ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By UOM</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_uom', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[_uom_code][]', 
	                    [ 'id'=>'_uom_code', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['_uom_code'] )? $this->filters['_uom_code'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Inconsistent Metric (kg/l)</label><br>
				<?php
	                $options = [ ''=>'All', '1'=>'Yes', '0'=>'No' ];
	                
	                wcwh_form_field( 'filter[inconsistent_unit]', 
	                    [ 'id'=>'inconsistent_unit', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['inconsistent_unit'] )? $this->filters['inconsistent_unit'] : '', $view 
	                ); 
				?>
			</div>

		<?php if( $this->setting['general']['use_item_storing_type'] ): ?>
			<div class="segment col-md-4">
				<label class="" for="flag">By Storing Type</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_store_type', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
	                
	                wcwh_form_field( 'filter[store_type_id]', 
	                    [ 'id'=>'store_type_id', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['store_type_id'] )? $this->filters['store_type_id'] : '', $view 
	                ); 
				?>
			</div>
		<?php endif; ?>
		</div>
	<?php
	}

	public function row_class( $class, $item )
	{
		if( ! $item['qty'] || $item['qty'] <= 0 )
			$class[] = "row-red";

		return $class;
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
			case 'transact_in':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-view" title="View Transaction In+" 
					data-id="'.$id.'" data-action="transact_in" data-service="'.$services.'" data-form="edit-wh_inventory" 
					data-modal="wcwhModalList" data-actions="close" data-title="View '.$title.' Transaction In+"
					'.$html_attr.' 
					><i class="fa fa-search"></i></a>';
			break;
			case 'transact_out':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-view" title="View Transaction Out-" 
					data-id="'.$id.'" data-action="transact_out" data-service="'.$services.'" data-form="edit-wh_inventory" 
					data-modal="wcwhModalList" data-actions="close" data-title="View '.$title.' Transaction Out-"
					'.$html_attr.' 
					><i class="fa fa-search"></i></a>';
			break;
			case 'transact':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-view" title="View Transactions" 
					data-id="'.$id.'" data-action="transact" data-service="'.$services.'" data-form="edit-wh_inventory" 
					data-modal="wcwhModalList" data-actions="close" data-title="View '.$title.' Transactions"
					'.$html_attr.' 
					><i class="fa fa-search"></i></a>';
			break;
			case 'pos_sales':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-view" title="POS Qty in a month" 
					data-id="'.$id.'" data-action="pos_sales" data-service="'.$services.'" data-form="edit-wh_inventory" 
					data-modal="wcwhModalList" data-actions="close" data-title="'.$title.' Latest POS Sales"
					'.$html_attr.' 
					><i class="fa fa-search"></i></a>';
			break;
		}

		return $btn;
	}

	public function print_final_footers( $datas = array(), $all_datas = array() ) 
	{
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		
		if( $all_datas )
		{
			foreach( $all_datas as $i => $data )
			{
				if(array_key_exists('qty',$data)){
					$qty += $data['qty'];
				}

				if(array_key_exists('qty',$data)){
					$allocated_qty += $data['allocated_qty'];
				}
			}

			$total = $qty-$allocated_qty;

			
		}
		
		$colspan = 'name';
		$colnull = [ '_uom_code','serial','total_in','total_out','qty','allocated_qty' ];
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
			if( $column_key == 'name' ) $column_display_name = '<b>TOTAL:</b>';
			if( $column_key == 'balance' ) $column_display_name = round_to( $total, 2, 1, 1 );
			

			echo "<$tag $scope $span $id $class>$column_display_name</$tag>";
		}
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

    /*
	public function column_name( $item ) 
	{
		$html = $item['name'];
		$args = [ 'id'=>$item['id'], 'service'=>'wh_items_action', 'title'=>$html, 'permission'=>[ 'access_wh_items' ] ];
		$html = $this->get_external_btn( $html, $args );

		return sprintf( '%1$s %2$s', '<strong>'.$html.'</strong>', $this->row_actions( $actions, true ) );  
	}*/

	public function column_name ( $item )
	{
		return '<strong>'.$item['name'].'</strong>';
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_serial( $item )
	{
		$code = [ 'code'=>$item['code'], 'sku'=>$item['_sku'], 'barcode'=>$item['serial'] ];
		$segments = [];
		foreach( $code as $key => $val )
		{
			if( ! $val ) continue;

			$segments[] = "<span class='toolTip' title='{$key}'>{$val}</span>";
		}

		$html = implode( ", ", $segments );
		
		return $html;
	}

	public function column_category( $item )
	{
		$html = $item['cat_slug'].' - '.$item['cat_name'];
		$args = [ 'id'=>$item['category'], 'service'=>'wh_items_category_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_category' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_item_group( $item )
	{
		$html = $item['grp_code'].' - '.$item['grp_name'];
		$args = [ 'id'=>$item['grp_id'], 'service'=>'wh_items_group_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_group' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_store_type( $item )
	{
		$html = $item['store_code'].' - '.$item['store_name'];
		$args = [ 'id'=>$item['store_type_id'], 'service'=>'wh_items_store_type_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_store_type' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_ref_by( $item )
	{
		$filter = [];
		if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
		if( strstr($item['ref_by'], ',') )
		{
			$ref = explode(",",$item['ref_by']);
			foreach ($ref as $key => $value) 
			{
				$filter['id'] = $value;
				$i = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
				$html[$key] = $i['code'].' - '.$i['name'];
				$args = [ 'id'=>$i['id'], 'service'=>'wh_items_action', 'title'=>$html[$key], 'permission'=>[ 'access_wh_items' ] ];
				$html[$key] = $this->get_external_btn( $html[$key], $args );
			}

			if($html) $html = implode(",<br>",$html);
		}
		else if ( $item['ref_by'])
		{
			$filter['id'] = $item['ref_by'];
			$i = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
			$html = $i['code'].' - '.$i['name'];
			$args = [ 'id'=>$i['id'], 'service'=>'wh_items_action', 'title'=>$html, 'permission'=>[ 'access_wh_items' ] ];
			$html = $this->get_external_btn( $html, $args );
		}

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) );
	}

	public function column_ret_by( $item )
	{
		$filter = [];
		if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
		if( strstr($item['ret_by'], ',') )
		{
			$ref = explode(",",$item['ret_by']);
			foreach ($ref as $key => $value) 
			{
				$filter['id'] = $value;
				$i = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
				$html[$key] = $i['code'].' - '.$i['name'];
				$args = [ 'id'=>$i['id'], 'service'=>'wh_items_action', 'title'=>$html[$key], 'permission'=>[ 'access_wh_items' ] ];
				$html[$key] = $this->get_external_btn( $html[$key], $args );
			}

			if($html) $html = implode(", ",$html);
		}
		else if ( $item['ret_by'])
		{
			$filter['id'] = $item['ret_by'];
			$i = apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
			$html = $i['code'].' - '.$i['name'];
			$args = [ 'id'=>$i['id'], 'service'=>'wh_items_action', 'title'=>$html, 'permission'=>[ 'access_wh_items' ] ];
			$html = $this->get_external_btn( $html, $args );
		}

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) );
	}

	public function column_total_in( $item )
	{
		$val = round_to( ( $item['total_in'] )? $item['total_in'] : 0, 2, 1, 1 );
		if( ! is_null( $item['total_in_'] ) && $item['total_in_'] != 0 )
			$sub_val = round_to( $item['total_in_'], 2, 1, 1 );

		$class = [];
		if( count(explode( ",", $item['sort_lvl'] )) > 1 ) $class[] = 'isChild';
		$class = implode( " ", $class );
		$html = "<span class='{$class}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		if( (! $item['parent'] || $this->canSupport) && $item['total_in'] > 0  ) $actions['transact_in'] = $this->get_action_btn( $item, 'transact_in', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_total_out( $item )
	{
		$val = round_to( ( $item['total_out'] )? $item['total_out'] : 0, 2, 1, 1 );
		if( ! is_null( $item['total_out_'] ) && $item['total_out_'] != 0 )
			$sub_val = round_to( $item['total_out_'], 2, 1, 1 );

		$class = [];
		if( count(explode( ",", $item['sort_lvl'] )) > 1 ) $class[] = 'isChild';
		$class = implode( " ", $class );
		$html = "<span class='{$class}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		if( (! $item['parent'] || $this->canSupport) && $item['total_out'] > 0 ) $actions['transact_in'] = $this->get_action_btn( $item, 'transact_out', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_qty( $item )
	{
		$val = round_to( ( $item['qty'] )? $item['qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['qty_'] ) && $item['qty_'] != 0 )
			$sub_val = round_to( $item['qty_'], 2, 1, 1 );

		$class = [];
		if( $item['qty'] <= 0 ) $class[] = 'clr-red';
		if( count(explode( ",", $item['sort_lvl'] )) > 1 ) $class[] = 'isChild';
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='Ori Qty: {$item['_qty']}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		if( ! $item['parent'] || $this->canSupport ) $actions['transact'] = $this->get_action_btn( $item, 'transact', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_allocated_qty( $item )
	{
		$val = round_to( ( $item['allocated_qty'] )? $item['allocated_qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['allocated_qty_'] ) && $item['allocated_qty_'] != 0 )
			$sub_val = round_to( $item['allocated_qty_'], 2, 1, 1 );

		$class = [];
		if( count(explode( ",", $item['sort_lvl'] )) > 1 ) $class[] = 'isChild';
		$class = implode( " ", $class );
		$html = "<span class='{$class}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		//if( $item['allocated_unit'] != 0 ) $html.= ", <sub>".wc_stock_amount( $item['allocated_unit'] )."</sub>";

		if( ! $item['parent'] && $item['allocated_qty'] > 0 )
			$actions['pos_sales'] = $this->get_action_btn( $item, 'pos_sales', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_total_sales_qty( $item )
	{
		$val = round_to( ( $item['total_sales_qty'] )? $item['total_sales_qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['total_sales_qty_'] ) && $item['total_sales_qty_'] != 0 )
			$sub_val = round_to( $item['total_sales_qty_'], 2, 1, 1 );

		$class = [];
		if( count(explode( ",", $item['sort_lvl'] )) > 1 ) $class[] = 'isChild';
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='Ori Qty: {$item['_total_sales_qty']}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		//if( $item['total_sales_unit'] != 0 ) $html.= ", <sub>".wc_stock_amount( $item['total_sales_unit'] )."</sub>";

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_balance( $item )
	{
		$item['balance'] = $item['qty'] - $item['allocated_qty'];
		$val = round_to( $item['balance'], 2, 1, 1 );
		$item['balance_'] = $item['qty_'] - $item['allocated_qty_'];

		if( ! is_null( $item['balance_'] ) && $item['balance_'] != 0 )
			$sub_val = round_to( $item['balance_'], 2, 1, 1 );

		$class = [];
		if( $item['balance'] <= 0 ) $class[] = 'clr-red';
		if( count(explode( ",", $item['sort_lvl'] )) > 1 ) $class[] = 'isChild';
		$class = implode( " ", $class );
		$html = "<span class='{$class}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		return sprintf( '%1$s', $html ); 
	}
	
} //class