<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Client_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_client";

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
			'cb'		=> '<input type="checkbox" />',
			"name" 		=> "Client",
			"code" 		=> "Code",
			"client_no" => "SAP Client No.",
			//"comp_id"	=> "Company",
			"tin"		=> "Tin No.",
			"parent" 	=> "Parent",
			"status"	=> "Status",
			"approval"	=> "Approval Status",
			"created"	=> "Created",
			"lupdate"	=> "Updated",
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
			'name' => [ "name", true ],
			'code' => [ "code", true ],
			'client_no' => [ "client_no", true ],
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
			),
		);
	}

	public function _filter_search()
	{
	?>
		<div class="row">
			<div class="col col-md-4">
				<?php
					
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
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['name'].'</strong>', $this->row_actions( $actions, true ) );  
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

	public function column_comp_id( $item )
	{
		$html = $item['comp_code']." - ".$item['comp_name'];
		$args = [ 'id'=>$item['comp_id'], 'service'=>'wh_company_action', 'title'=>$html, 'permission'=>[ 'access_wh_company' ] ];
		return $this->get_external_btn( $html, $args );
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
		$html = implode( " > ", $elem );
		
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