<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_SYNC_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_sync";

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

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}
	
	public function get_columns() 
	{
		return array(
			'cb'			=> '<input type="checkbox" />',
			"id" 			=> "ID",
			"ref"			=> "Ref. No.",
			"direction" 	=> "Direction",
			"remote_url"	=> "Remote Url",
			"wh_code"		=> "Warehouse",
			"section" 		=> "Section",
			"ref_id"		=> "Ref ID",
			//"ref"			=> "Action Ref",
			"details"		=> "Data",
			"status"		=> "Status",
			"handshake"		=> "handshake",
			"notification"	=> "Notice",
			"created_at"	=> "Created",
			"lsync_at"		=> "Last Sync",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'details' );

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'id' => [ "id", true ],
			'ref' => [ "ref", true ],
			'direction' => [ "direction", true ],
			'wh_code' => [ "wh_code", true ],
			'section' => [ "section", true ],
			'ref_id' => [ "ref_id", true ],
			'created_at' => [ "created_at", true ],
			'lsync_at' => [ "lsync_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		$actions['delete'] = $this->refs['actions']['delete'];
		
		return $actions;
	}

	public function get_data_alters( $datas = array() )
	{
		return $datas;
	}

	public function get_statuses()
	{
		return array(
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'1'		=> array( 'key' => 'active', 'title' => 'Ready' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'Cancelled' ),
		);
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
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_support' ],
				'sync' => [ 'wh_support' ],
				'delete' => [ 'wh_support' ],
			),
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Warehouse</label>
				<?php
					$warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1 ], [], false, [] );
					$options = options_data( $warehouses, 'code', [ 'code', 'name' ] );

					wcwh_form_field( 'filter[wh_code]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options, 'offClass'=>true
		                ], 
		                isset( $this->filters['wh_code'] )? $this->filters['wh_code'] : '', $view 
		             ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Section</label>
				<?php
					$sections = get_sections();
					if( $sections )
					{
						$s = $sections; $sections = [];
						foreach( $s as $i => $section )
						{
							if( $section['push_service'] )
							$sections[] = $section;
						}
					}
						
					$options = options_data( $sections, 'section_id', [ 'section_id', 'desc' ], '' );

					wcwh_form_field( 'filter[section][]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                    'options'=> $options, 'offClass'=>true, 'multiple'=>1
		                ], 
		                isset( $this->filters['section'] )? $this->filters['section'] : '', $view 
		             ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Direction</label><br>
				<?php
					$options = [ ''=>'All', 'in'=>'In', 'out'=>'Out' ];
                
		            wcwh_form_field( 'filter[direction]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['direction'] )? $this->filters['direction'] : '', $view 
		            ); 
				?>
			</div>
			<div class="segment col-md-4">
				<label class="" for="flag">By Handshaked</label><br>
				<?php
					$options = [ ''=>'All', '1'=>'Yes', '0'=>'No' ];
	                
	                wcwh_form_field( 'filter[handshake]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['handshake'] )? $this->filters['handshake'] : '', $view 
	                ); 
				?>
			</div>
		</div>
	<?php
	}


	public function get_title_hyperlink( $item )
	{
		$html = '';
		switch( $item['section'] )
		{
			case 'wh_items':
			case 'wh_pricing':
			case 'wh_promo':
			case 'wh_delivery_order':
			case 'wh_good_return':
			case 'wh_purchase_request':
			case 'wh_closing_pr':
			case 'wh_items_group':
			case 'wh_items_category':
			case 'wh_uom':
			case 'wh_acc_period':
			case 'wh_exchange_rate':
			case 'wh_service_charge':
				$html = $item['ref'];
			
				$args = [ 'id'=>$item['ref_id'], 'service'=>$item['section'].'_action', 'title'=>$html, 'permission'=>[ 'access_'.$item['section'] ] ];

				$html = $this->get_external_btn( $html, $args );
			break;
			default:
				$html = $item['ref'];
			break;
		}

		return $html;
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

    public function column_ref( $item ) 
	{	
		$stat = ( $item['status'] && !$item['handshake'] )? 1 : 0;
		$actions = $this->get_actions( $item, $stat );

		$title = $this->get_title_hyperlink( $item );
		
		return sprintf( '%1$s %2$s', '<strong>'.$title.'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}
	
} //class