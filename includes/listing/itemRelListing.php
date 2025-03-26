<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_ItemRel_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_items_relation";

	public $useFlag;

	private $users;

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
			"code" 			=> "Code",
			"_uom_code"		=> "UOM",
			"category"		=> "Category",
			"rel_type"		=> "Relation Type",
			"reorder_type"	=> "Order Type",
			"status"		=> "Status",
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
					$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'usage'=>0 ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[items_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['items_id'] )? $this->filters['items_id'] : '', $view 
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

	public function column_reorder_type( $item )
	{
		if( $item['order_type_code'] )
		$html = $item['order_type_code']." - ".$item['order_type_name']."<br>[ Lead Time: {$item['lead_time']} ], [ Order Period: {$item['order_period']} ]";

		return $html;
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_category( $item )
	{
		$html = $item['cat_slug'].' - '.$item['cat_name'];
		$args = [ 'id'=>$item['category'], 'service'=>'wh_items_relation_category_action', 'title'=>$html, 'permission'=>[ 'access_wh_items_relation_category' ] ];
		return $this->get_external_btn( $html, $args );
	}
	
} //class