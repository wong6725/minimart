<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_MailLog_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_mail_logs";

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
			"id"		=> "ID",
			"mail_id" 	=> "Mail ID",
			"section" 	=> "Section",
			"ref_id"	=> "Ref ID",
			"status"	=> "Status",
			"error_remark"	=> "Err Info",
			"action_by"	=> "Actor",
			"log_at"	=> "Time",
			"ip_address"=> "IP Addr",
			"data"		=> "Data",
		);
	}

	public function get_hidden_column()
	{
		$col = array( "data" );

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'id' => [ "id", true ],
			'mail_id' => [ "mail_id", true ],
			'section' => [ "section", true ],	
			'log_at' => [ "log_at", true ],
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

	public function get_statuses()
	{
		return array(
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'1'		=> array( 'key' => 'active', 'title' => 'Success' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'Failed' ),
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
			'default' => array(
				'view' => [ 'wh_support', 'wh_admin_support' ],
			),
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="col col-md-4">
				<label class="" for="flag">By Section</label>
				<?php
					$options = options_data( get_sections(), 'section_id', [ 'desc', 'section_id' ] );
                
	                wcwh_form_field( 'filter[section]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['section'] )? $this->filters['section'] : '', $view 
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
	public function column_id( $item ) 
	{	
		$actions = $this->get_actions( $item );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['id'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_action_by( $item )
	{
		$user = ( $this->users )? $this->users[ $item['action_by'] ] : $item['action_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		
		return $user;
	}
	
} //class