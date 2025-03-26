<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Inventory_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_inventory";

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
			'cb'			=> '<input type="checkbox" />',
			"name" 			=> "Name",
			"_uom_code"		=> "UOM",
			"serial"		=> "Barcode",
			"item_group"	=> "Group",
			"brand"			=> "Brand",
			"category"		=> "Category",
			"store_type"	=> "Storing",
			"parent" 		=> "Parent",
			"conversion"	=> "Base Conversion",
			"total_in"		=> "IN+",
			"total_out"		=> "OUT-",
			"qty"			=> "Stocks",
			"reserved_qty"	=> "Reserved",
			"allocated_qty"	=> "POS Sales",
			//"total_sales_qty" => "Sales",
			"balance"		=> "Balance",
		);
	}

	public function get_hidden_column()
	{
		$hidden = [ 'parent' ];
		
		$opts = $this->setting[ $this->section_id ];

		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] )
		{
			$this->setting = WCWH_Function::get_setting( '', '', $this->warehouse['id'] );
			$opts = $this->setting[ $this->section_id ];
		}
		
		if( ! $opts['use_allocate'] )
		{
			$hidden[] = 'allocated_qty';
		}
		if( ! $opts['use_reserved'] )
		{
			$hidden[] = 'reserved_qty';
		}
		if( ! $opts['use_allocate'] && ! $opts['use_reserved'] )
		{
			$hidden[] = 'balance';
		}

		return $hidden;
	}

	public function get_sortable_columns()
	{
		return array();
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'save_wh_purchase_request' ) )
			$actions['wh_purchase_request'] = 'Purchase Request';
		if( current_user_can( 'save_wh_purchase_order' ) )
			$actions['wh_purchase_order'] = 'Purchase Order';
		if( current_user_can( 'save_wh_sales_order' ) )
			$actions['wh_sales_order'] = 'Sales Order';
		if( current_user_can( 'save_own_use_wh_good_issue' ) )
			$actions['wh_good_issue_own_use'] = 'Company Use';
		if( current_user_can( 'save_block_stock_wh_good_issue' ) )
			$actions['wh_good_issue_block_stock'] = 'Block Stock';
		//if( current_user_can( 'save_wh_stock_adjust' ) )
		//	$actions['wh_stock_adjust'] = 'Stock Adjustment';
		
		return $actions;
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		$this->search_box( 'Search', "s" );
		$this->prepare_items();
		$this->display();
	}

	public function filter_search()
	{
		$opts = $this->setting[ $this->section_id ];
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

			<div class="segment col-md-4">
				<label class="" for="flag">By Item Group</label><br>
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

			<div class="segment col-md-6">
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

			<div class="segment col-md-6">
				<label class="" for="flag">By Items</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', '_sku', '_uom_code', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[item_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['item_id'] )? $this->filters['item_id'] : '', $view 
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
					$options = options_data( apply_filters( 'wcwh_get_store_type', $filter, [], false, [] ), 'id', [ 'code', 'name' ], 'Select', [ 'not'=>'Not Specify' ] );
	                
	                wcwh_form_field( 'filter[store_type_id]', 
	                    [ 'id'=>'store_type_id', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['store_type_id'] )? $this->filters['store_type_id'] : '', $view 
	                ); 
				?>
			</div>
		<?php endif; ?>

			<div class="segment col-md-4">
				<label class="" for="flag">By Stock Condition</label><br>
				<?php
	                $options = [ 'all'=>'All', 'yes'=>'Have Stock', 'no'=>'No Stock', 'zero'=>'0 Stock', 'below'=>'Below 0' ];
	                
	                wcwh_form_field( 'filter[stock_condition]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['stock_condition'] )? $this->filters['stock_condition'] : 'positive', $view 
	                ); 
				?>
			</div>

		<?php if( $opts['use_reserved'] ): ?>
			<div class="segment col-md-4">
				<label class="" for="flag">By Reserved Condition</label><br>
				<?php
	                $options = [ 'all'=>'All', 'yes'=>'Reserved', 'no'=>'None Reserved' ];
	                
	                wcwh_form_field( 'filter[reserve_condition]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['reserve_condition'] )? $this->filters['reserve_condition'] : 'all', $view 
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
			case 'reserved':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-view" title="View Reserved" 
					data-id="'.$id.'" data-action="reserved" data-service="'.$services.'" data-form="edit-wh_inventory" 
					data-modal="wcwhModalList" data-actions="close" data-title="View '.$title.' Reserved"
					'.$html_attr.' 
					><i class="fa fa-search"></i></a>';
			break;
			case 'movement':
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-view" title="View Movement" 
					data-id="'.$id.'" data-action="movement" data-service="'.$services.'" data-form="edit-wh_inventory" 
					data-modal="wcwhModalList" data-actions="close" data-title="View '.$title.' Movement"
					'.$html_attr.' 
					><i class="fa fa-list"></i></a>';
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


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

	public function column_name( $item ) 
	{	
		//$actions = $this->get_actions( $item, $item['status'] );

		$html = $item['name'];
		$args = [ 'id'=>$item['id'], 'service'=>'wh_items_action', 'title'=>$html, 'permission'=>[ 'access_wh_items' ] ];
		$html = $this->get_external_btn( $html, $args );
		
		$indent = "";
		if( $item['breadcrumb_id'] && $item['status'] )
		{	
			if( $item['breadcrumb_status'] ) $stat = explode( ",", $item['breadcrumb_status'] );
			$ids = explode( ",", $item['breadcrumb_id'] );
			foreach( $ids as $i => $id )
			{
				if( $id == $item['id'] || $stat[ $i ] <= 0 ) continue;
				$indent.= "— ";
			}
		}
		$indent = ( $indent )? "<span class='displayBlock'>{$indent}</span>" : "";

		$indent = "<span class='displayBlock'>{$indent}</span>";

		return sprintf( '%1$s %2$s', $indent.'<strong>'.$html.'</strong>', $this->row_actions( $actions, true ) );  
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

	public function column_parent( $item )
	{
		if( $item['breadcrumb_id'] ) $ids = explode( ",", $item['breadcrumb_id'] );
		if( $item['breadcrumb_status'] ) $stat = explode( ",", $item['breadcrumb_status'] );
		if( $item['breadcrumb_code'] ) $codes = explode( ",", $item['breadcrumb_code'] );

		$elem = [];
		if( $codes )
		foreach( $codes as $i => $code )
		{
			if( $ids[ $i ] == $item['id'] || $stat[ $i ] <= 0 ) continue;
			$args = [ 'id'=>$ids[ $i ], 'service'=>'wh_items_action', 'title'=>$code ];
			$elem[] = $this->get_external_btn( $code, $args );
		}
		if( $elem ) $elem[] = $item['code'];
		$html = implode( " > ", $elem );
		
		return $html;
	}

	public function column_conversion( $item )
	{
		return ( $item['conversion'] && $item['parent'] )? round_to( $item['conversion'], 0, 1, 1 ) : '';;
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

	public function column_brand( $item )
	{
		$html = $item['brand_code'].' - '.$item['brand_name'];
		$args = [ 'id'=>$item['brand_id'], 'service'=>'wh_brand_action', 'title'=>$html, 'permission'=>[ 'access_wh_brand' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_total_in( $item )
	{
		$val = $def_val = round_to( ( $item['total_in'] )? $item['total_in'] : 0, 2, 1, 1 );
		if( ! is_null( $item['total_in_'] ) && $item['total_in_'] != 0 )
			$sub_val = round_to( $item['total_in_'], 2, 1, 1 );

		$class = [];
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		if( ! $item['parent'] || $this->canSupport ) $actions['transact_in'] = $this->get_action_btn( $item, 'transact_in', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_total_out( $item )
	{
		$val = $def_val = round_to( ( $item['total_out'] )? $item['total_out'] : 0, 2, 1, 1 );
		if( ! is_null( $item['total_out_'] ) && $item['total_out_'] != 0 )
			$sub_val = round_to( $item['total_out_'], 2, 1, 1 );

		$class = [];
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		if( ! $item['parent'] || $this->canSupport ) $actions['transact_in'] = $this->get_action_btn( $item, 'transact_out', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_qty( $item )
	{
		$val = $def_val = round_to( ( $item['qty'] )? $item['qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['qty_'] ) && $item['qty_'] != 0 )
			$sub_val = round_to( $item['qty_'], 2, 1, 1 );

		$class = [];
		if( $item['qty'] <= 0 ) $class[] = 'clr-red';
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		if( $this->canSupport ) $actions['movement'] = $this->get_action_btn( $item, 'movement', [] );
		if( ! $item['parent'] || $this->canSupport ) $actions['transact'] = $this->get_action_btn( $item, 'transact', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_allocated_qty( $item )
	{
		$val = $def_val = round_to( ( $item['allocated_qty'] )? $item['allocated_qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['allocated_qty_'] ) && $item['allocated_qty_'] != 0 )
			$sub_val = round_to( $item['allocated_qty_'], 2, 1, 1 );

		$class = [];
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		//if( $item['allocated_unit'] != 0 ) $html.= ", <sub>".wc_stock_amount( $item['allocated_unit'] )."</sub>";

		if( $item['allocated_qty'] > 0 )
			$actions['pos_sales'] = $this->get_action_btn( $item, 'pos_sales', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_reserved_qty( $item )
	{
		$val = $def_val = round_to( ( $item['reserved_qty'] )? $item['reserved_qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['reserved_qty_'] ) && $item['reserved_qty_'] != 0 )
			$sub_val = round_to( $item['reserved_qty_'], 2, 1, 1 );

		$class = [];
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		//if( $item['allocated_unit'] != 0 ) $html.= ", <sub>".wc_stock_amount( $item['allocated_unit'] )."</sub>";

		if( $item['reserved_qty'] > 0 )
			$actions['reserved'] = $this->get_action_btn( $item, 'reserved', [] );

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_total_sales_qty( $item )
	{
		$val = $def_val = round_to( ( $item['total_sales_qty'] )? $item['total_sales_qty'] : 0, 2, 1, 1 );
		if( ! is_null( $item['total_sales_qty_'] ) && $item['total_sales_qty_'] != 0 )
			$sub_val = round_to( $item['total_sales_qty_'], 2, 1, 1 );

		$class = [];
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		//if( $item['total_sales_unit'] != 0 ) $html.= ", <sub>".wc_stock_amount( $item['total_sales_unit'] )."</sub>";

		return sprintf( '%1$s %2$s', $html, $this->row_actions( $actions, true ) ); 
	}

	public function column_balance( $item )
	{
		$item['balance'] = $item['qty'] - $item['reserved_qty'] - $item['allocated_qty'];
		$val = $def_val = round_to( $item['balance'], 2, 1, 1 );
		$item['balance_'] = $item['qty_'] - $item['reserved_qty_'] - $item['allocated_qty_'];

		if( ! is_null( $item['balance_'] ) && $item['balance_'] != 0 )
			$sub_val = round_to( $item['balance_'], 2, 1, 1 );

		$class = [];
		if( $item['balance'] <= 0 ) $class[] = 'clr-red';
		if( $item['parent'] > 0 ) 
		{
			$class[] = 'isChild';
			$val = round_to( floor( $val ), 2, 1, 1 );
		}
		$class = implode( " ", $class );
		$html = "<span class='{$class}' title='{$def_val}'>{$val}</span>";
		if( $sub_val ) $html.= "<br><span class='subQty {$class}' title='Base Item Left'>{$sub_val}</span>";

		return sprintf( '%1$s', $html ); 
	}
	
} //class