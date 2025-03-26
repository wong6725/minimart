<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_User_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_maintain_user";

	protected $usable_roles = [];

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

	public function set_usable_roles( $roles )
	{
		$this->usable_roles = $roles;
	}
	
	public function get_columns() 
	{
		return array(
			'cb'			=> '<input type="checkbox" />',
			"user_login" 	=> "Username",
			"user_email" 	=> "Email",
			"name" 			=> "Name",
			"role"			=> "Role",
			"status"		=> "Status",
			"start_date" => "Effective From",
			"end_date" => "Effective To",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			"user_pass"		=> "Encrypted Password",
			"last_login"	=> "Last Login",
		);
	}

	public function get_hidden_column()
	{
		$col = [];
		if( ! $this->useFlag ) $col[] = 'approval';

		if( ! current_user_cans( ['wh_super_admin'] ) ){ $col[] = 'user_pass'; $col[] = 'last_login'; }

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'user_login' => [ "user_login", true ],
			'user_email' => [ "user_email", true ],
			'role' => [ "role", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = [];
		
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
			'1'		=> array( 'key' => 'active', 'title' => 'Active' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'Deactive' ),
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
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support', 'manage_pos_order' ],
			),
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="col-md-4">
				<label class="" for="flag">By Role </label><br>
				<?php
					$options = $this->usable_roles;
                
		            wcwh_form_field( 'filter[role][]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
		                    'options'=> $options, 'multiple'=>1
		                ], 
		                isset( $this->filters['role'] )? $this->filters['role'] : '', $view 
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

	public function column_user_login( $item ) 
	{	
		$actions = $this->get_actions( $item, 'default' );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['user_login'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_name( $item )
	{
		$html = !empty( $item['name'] )? $item['name'] : $item['display_name'];

		return $html;
	}

	public function column_role( $item )
	{
		$role = maybe_unserialize( $item['role'] );
		$role = array_keys($role);

		$roles = [];
		foreach( $role as $r ) $roles[] = $this->usable_roles[ $r ];

		return implode( ", ", $roles );
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

		return $html;
	}

	public function column_created( $item )
	{
		return $item['created_at'];
	}

	public function column_lupdate( $item )
	{
		return $item['lupdate_at'];
	}
	
	//----------------04/10/2022
	public function column_start_date( $item )
	{
		$current_date = current_time('Y-m-d');
		$class = '';

		if($item['start_date'] && $current_date < $item['start_date']) $class = 'clr-red';
		$html = "<strong><span class='{$class}'>{$item['start_date']}</span></strong>";
		return sprintf( '%1$s', $html ); 
	}
	
	public function column_end_date( $item )
	{
		$current_date = current_time('Y-m-d');
		$class = '';

		if($item['end_date'] && $current_date > $item['end_date']) $class = 'clr-red';
		$html = "<strong><span class='{$class}'>{$item['end_date']}</span></strong>";
		return sprintf( '%1$s', $html ); 
	}
	//----------------04/10/2022
	
} //class