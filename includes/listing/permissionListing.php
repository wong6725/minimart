<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Permission_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_permission";

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
			'cb'		=> '<input type="checkbox" />',
			"ref_id" 	=> "Role / User",
			"scheme" 	=> "Scheme",
			"scheme_lvl"=> "Level",
			"permission"=> "Permissions"
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'scheme' => [ "scheme", true ],
			'scheme_lvl' => [ "scheme_lvl", true ],
		];

		return $col;
	}

	public function get_manual_sortable_columns()
	{
		$col = [
			'ref_id' => [ "name", true ],
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
		if( $datas )
		{
			foreach( $datas as $i => $item )
			{
				$datas[$i]['name'] = $item['ref_id'];
				switch( $item['scheme'] )
				{
					case 'role':
						global $wp_roles;
						$roles = $wp_roles->roles;
						$datas[$i]['name'] = $roles[ $item['ref_id'] ]['name'];
					break;
					case 'user':
						$user_info = get_userdata( $item['ref_id'] );
						$datas[$i]['name'] = ( $user_info )? $user_info->display_name : $value;
					break;
				}
			}
		}

		return $datas;
	}

    public function render()
    {
		if( ! $this ) return;

		$this->search_box( 'Search', "s" );
		$this->prepare_items();
		$this->display();
	}

	public function get_styles()
	{
		return $this->styles = [
			'#permission' => [
				'width' => '45vw',
			],
		];
	}

	public function get_status_action( $item )
	{
		return [
			'default' => [
				'view' => [ 'wh_manage_permission', 'wh_view_permission' ],
				'edit' => [ 'wh_manage_permission' ],
			],
			'user' => [
				'view' => [ 'wh_manage_permission', 'wh_view_permission' ],
				'edit' => [ 'wh_manage_permission' ],
				'delete' => [ 'wh_manage_permission' ],
			],
		];
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

	public function column_ref_id( $item ) 
	{	
		$actions = [];
		switch( $item['scheme'] )
		{
			case 'role':
				$actions = $this->get_actions( $item );
			break;
			case 'user':
				$actions = $this->get_actions( $item, 'user' );
			break;
		}
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_permission( $item )
	{
		$data = json_decode( $item['permission'], true );
		return ( $data )? implode( ', ', $data ) : '';
	}
	
} //class