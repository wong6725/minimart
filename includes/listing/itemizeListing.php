<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Itemize_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_itemize";

	public $useFlag;

	protected $users;

	private $canSupport = false;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->users = get_simple_users();

		if( current_user_cans( [ 'wh_support' ] ) )
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
			"item_name" 	=> "Item",
			"item_uom"		=> "UOM",
			"name" 			=> "Content",
			"code"			=> "Code",
			"serial"		=> "Serial",
			"expiry"		=> "Expiry",
			"in_stock"		=> "In Stock",
			"status"		=> "Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			"receipt"		=> "Receipt",
		);
	}

	public function get_hidden_column()
	{
		$col = [];
		if( ! $this->useFlag ) $col[] = 'approval';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'item_name' => [ "item_name", true ],
			'name' => [ "name", true ],
			'code' => [ "code", true ],
			'serial' => [ "serial", true ],
			'expiry' => [ "expiry", true ],
			'created' => [ "created_at", true ],
			'lupdate' => [ "lupdate_at", true ],
		];

		return $col;
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

		$this->search_box( 'Search', "s" );
		$this->prepare_items();
		$this->display();
	}
	
	public function get_statuses()
	{
		return array(
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'1'		=> array( 'key' => 'active', 'title' => 'Yes' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'No' ),
			'-1'	=> array( 'key' => 'deleted', 'title' => 'Deleted' ),
		);
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
				'delete' => [ 'delete_'.$this->section_id ],
			)
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Virtual Item</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
	                $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'virtual'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ] );
					
	                wcwh_form_field( 'filter[product_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options,
	                    ], 
	                    isset( $this->filters['product_id'] )? $this->filters['product_id'] : '', $view 
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

	public function column_item_name( $item ) 
	{	
		if( $this->warehouse['id'] && $this->view_outlet )
			$actions = [];
		else
			$actions = $this->get_actions( $item, $item['status'] );
		
		return sprintf( '%1$s %2$s', $indent.'<strong>'.$item['item_name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_in_stock( $item )
	{
		$stat = ( $item['stock'] )? 'yes' : 'no';
		$html = "<span class='list-stat list-{$stat}'>{$stat}</span>";

		return $html;
	}

	public function column_serial( $item )
	{
		if( $this->canSupport )
			return $item['serial'];
		
		$len = strlen( $item['serial'] );
		$serial = str_pad( substr( $item['serial'], 0, 3 ), $len-4, "*" ).substr( $item['serial'], $len-4 );

		return $serial;
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