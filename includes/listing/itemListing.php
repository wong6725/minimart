<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Item_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_items";

	public $useFlag;

	private $users;

	public $warehouse;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->users = get_simple_users();
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
			"name" 			=> "Item",
			"uom_code"		=> "UOM",
			"_sku" 			=> "SKU",
			"code" 			=> "Code",
			"serial"		=> "Barcode",
			"item_group"	=> "Group",
			"brand"			=> "Brand",
			"category"		=> "Category",
			"store_type"	=> "Storing",
			"parent" 		=> "Base Item",
			"inconsistent"	=> "Metric <sup class='toolTip' title='' data-original-title='Inconsistent Metric (kg/l)'> ? </sup>",
			"scale_key"		=> "Key <sup class='toolTip' title='' data-original-title='Weight Scale Key'> ? </sup>",
			"_sellable"		=> "Sellable",
			"status"		=> "Status",
			"approval"		=> "Approval",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
		);
	}

	public function get_hidden_column()
	{
		$col = [ "store_type" ];
		if( ! $this->useFlag ) $col[] = 'approval';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'name' => [ "name", true ],
			'_sku' => [ "_sku", true ],
			'code' => [ "code", true ],
			'serial' => [ "serial", true ],
			'item_group' => [ "grp_code", true ],
			'brand' => [ "brand_code", true ],
			'category' => [ "cat_slug", true ],
			'created' => [ "created_at", true ],
			'lupdate' => [ "lupdate_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'delete_'.$this->section_id ) )
			$actions['delete'] = $this->refs['actions']['delete'];
		
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

	public function get_status_action( $item )
	{
		return array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
				'restore' => [ 'restore_'.$this->section_id ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
			)
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Items</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['id'] )? $this->filters['id'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By UOM</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_uom', [], [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
	                wcwh_form_field( 'filter[_uom_code][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['_uom_code'] )? $this->filters['_uom_code'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Group</label><br>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_item_group', [], [], false, [] ), 'id', [ 'code', 'name' ] );
                
	                wcwh_form_field( 'filter[grp_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['grp_id'] )? $this->filters['grp_id'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Brand</label><br>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_brand', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
		            wcwh_form_field( 'filter[_brand][]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['_brand'] )? $this->filters['_brand'] : '', $view 
		            ); 
				?>
			</div>
		
			<div class="segment col-md-4">
				<label class="" for="flag">By Category</label><br>
				<?php
                	$options = options_data( apply_filters( 'wcwh_get_item_category', [], [], false, [] ), 'term_id', [ 'slug', 'name' ], '' );
	                
	                wcwh_form_field( 'filter[category][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['category'] )? $this->filters['category'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Inconsistent Metric (kg/l)</label><br>
				<?php
	                $options = [ ''=>'All', '1'=>'Yes', '0'=>'No' ];
	                
	                wcwh_form_field( 'filter[inconsistent_unit]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['inconsistent_unit'] )? $this->filters['inconsistent_unit'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Weight Scale</label><br>
				<?php
	                $options = [ ''=>'All', 'yes'=>'Yes', 'no'=>'No' ];
	                
	                wcwh_form_field( 'filter[has_scale_key]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['has_scale_key'] )? $this->filters['has_scale_key'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Storage Type</label><br>
				<?php
	                $options = options_data( apply_filters( 'wcwh_get_store_type', [], [], false, [] ), 'id', [ 'code', 'name' ] );
	                
	                wcwh_form_field( 'filter[store_type_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['store_type_id'] )? $this->filters['store_type_id'] : '', $view 
	                ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Order Type</label><br>
				<?php
	                $filter = [ 'status'=>1 ];
		            if( $this->warehouse['code'] ) $filter['wh_code'] = $this->warehouse['code'];
		            $options = options_data( apply_filters( 'wcwh_get_order_type', $filter, [], false, [] ), 'id', [ 'code', 'name', 'lead_time', 'order_period' ] );

		            wcwh_form_field( 'filter[reorder_type]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options, 
		                ], 
		                isset( $this->filters['reorder_type'] )? $this->filters['reorder_type'] : '', $view 
		            ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Sellable</label><br>
				<?php
	                $options = [ '' => 'All', 'yes' => 'Sellable', 'no' => "Not For Sale", 'force' => 'Force Sellable' ];

		            wcwh_form_field( 'filter[sellable]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options, 
		                ], 
		                isset( $this->filters['sellable'] )? $this->filters['sellable'] : '', $view 
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
	public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

	public function column_name( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'] );

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
		
		return sprintf( '%1$s %2$s', $indent.'<strong>'.$item['name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_approval( $item )
	{
		$approval = $this->get_approvals();
		$html = "<span class='list-stat list-{$approval[ $item['flag'] ]['key']}'>{$approval[ $item['flag'] ]['title']}</span>";

		return $html;
	}

	public function column__sku( $item )
	{
		$actions = [];
		$html = [];
		
		if( $item['_sku'] ) 
		{	
			$lbl_name = get_items_meta( $item['id'], 'label_name', true );
			$datas = [ 'dataitem'=>( $lbl_name )? $lbl_name : $item['name'], 'dataserial'=>$item['_sku'] ];
			
			$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$item['_sku'], 'tpl'=>'product_label', 'datas'=>$datas ] );
			$actions['barcode'] = $this->get_action_btn( $item, 'barcode', [ 'serial'=>$item['_sku'], 'tpl'=>'product_label', 'datas'=>$datas ] );
		}
		$html[] = sprintf( '%1$s %2$s', ( $item['_sku']? $item['_sku'] : '-' ), $this->row_actions( $actions, true ) );

		if( is_json( $item['serial2'] ) ) $item['serial2'] = json_decode( stripslashes( $item['serial2'] ), true );
		if( $item['serial2'] && ! is_array( $item['serial2'] ) ) $item['serial2'] = [ $item['serial2'] ];
		if( $item['serial2'] )
		{
			foreach( $item['serial2'] as $serial2 )
			{
				$actions = [];
				$lbl_name = get_items_meta( $item['id'], 'label_name', true );
				$datas = [ 'dataitem'=>( $lbl_name )? $lbl_name : $item['name'], 'dataserial'=>$serial2 ];
				
				$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$serial2, 'tpl'=>'product_label', 'datas'=>$datas ] );
				$actions['barcode'] = $this->get_action_btn( $item, 'barcode', [ 'serial'=>$serial2, 'tpl'=>'product_label', 'datas'=>$datas ] );

				$html[] = sprintf( '%1$s %2$s', $serial2, $this->row_actions( $actions, true ) );
			}
		}

		return implode( ' ', $html ); 
	}

	public function column_code( $item )
	{
		$actions = array();
		
		if( $item['code'] ) 
		{	
			$lbl_name = get_items_meta( $item['id'], 'label_name', true );
			$datas = [ 'dataitem'=>( $lbl_name )? $lbl_name : $item['name'], 'dataserial'=>$item['code'] ];
			
			$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$item['code'], 'tpl'=>'product_label', 'datas'=>$datas ] );
			$actions['barcode'] = $this->get_action_btn( $item, 'barcode', [ 'serial'=>$item['code'], 'tpl'=>'product_label', 'datas'=>$datas ] );
		}
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['code'].'</strong>', $this->row_actions( $actions, true ) ); 
	}

	public function column_serial( $item )
	{
		$actions = array();
		
		if( $item['serial'] ) 
		{	
			$lbl_name = get_items_meta( $item['id'], 'label_name', true );
			$datas = [ 'dataitem'=>( $lbl_name )? $lbl_name : $item['name'], 'dataserial'=>$item['serial'] ];
			
			$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$item['serial'], 'tpl'=>'product_label', 'datas'=>$datas ] );
			$actions['barcode'] = $this->get_action_btn( $item, 'barcode', [ 'serial'=>$item['serial'], 'tpl'=>'product_label', 'datas'=>$datas ] );
		}

		return sprintf( '%1$s %2$s', $item['serial'], $this->row_actions( $actions, true ) ); 
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
			$args = [ 'id'=>$ids[ $i ], 'service'=>$this->section_id.'_action', 'title'=>$code ];
			$elem[] = $this->get_external_btn( $code, $args );
		}
		if( $elem ) $elem[] = $item['code'];
		$html = ( $elem )? implode( " > ", $elem ) : '-';
		
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

	public function column_brand( $item )
	{
		$html = $item['brand_code'].' - '.$item['brand_name'];
		$args = [ 'id'=>$item['brand_id'], 'service'=>'wh_brand_action', 'title'=>$html, 'permission'=>[ 'access_wh_brand' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_inconsistent( $item )
	{
		return ( $item['inconsistent_unit'] )? 'Yes' : '-';
	}

	public function column_scale_key( $item )
	{
		return ( $item['_weight_scale_key'] )? $item['_weight_scale_key'] : '-';
	}

	public function column__sellable( $item )
	{
		$item['_sellable'] = ( $item['_sellable'] )? $item['_sellable'] : 'yes';
		$html = "<span class='list-stat list-{$item['_sellable']}'>".ucfirst( $item['_sellable'] )."</span>";

		return $html;
	}

	public function column_created( $item )
	{
		$user = ( $this->users )? $this->users[ $item['created_by'] ] : $item['created_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['created_at'];

		$html = $user.'<br>'.$date;
		
		$args = [ 
			'action' => 'view_doc', 
			'id' => $item['id'], 
			'service' => 'wh_stage_action', 
			'title' => $html, 
			'desc' => 'View State Change',
			'permission' => [ 'wcwh_user' ] 
		];
		return $this->get_external_btn( $html, $args );
	}

	public function column_lupdate( $item )
	{
		$user = ( $this->users )? $this->users[ $item['lupdate_by'] ] : $item['lupdate_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['lupdate_at'];

		$html = $user.'<br>'.$date;

		$args = [ 
			'action' => 'view_doc', 
			'id' => $item['id'], 
			'service' => 'wh_logs_action', 
			'title' => $html, 
			'desc' => 'View History',
			'permission' => [ 'wcwh_user' ] 
		];
		return $this->get_external_btn( $html, $args );
	}
	
} //class