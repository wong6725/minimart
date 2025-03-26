<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Permission_Class" ) ) include_once( WCWH_DIR . "/includes/classes/permission.php" ); 

if ( !class_exists( "WCWH_Permission_Controller" ) ) 
{

class WCWH_Permission_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_permission";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Permission_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newPermission',
	);

	protected $section = 'permission';

	protected $refs;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_Permission_Class( $this->db_wpdb );
	}

	public function get_section_id()
	{
		return $this->section_id;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'scheme' => 'role',
			'scheme_lvl' => 0,
			'ref_id' => '',
			'permission' => '',
		);
	}

	protected function get_uniqueFields()
	{
		return array();
	}

	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
			'filter',
			'_wpnonce',
			'action2',
			'_wp_http_referer',
		);
	}

	public function validate( $action , $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $action || ! $datas )
		{
			$succ = false;
			$this->Notices->set_notice( 'insufficient-data', 'error' );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch( $action )
			{
				case 'update':
				case 'delete':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
			}
		}

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$datas = $this->data_sanitizing( $datas );
					
					if( $datas['permission'] )
					{
						$dat = array();
						foreach( $datas['permission'] as $permission => $val )
						{
							if( $val ) $dat[] = $permission;
						}
						$datas['permission'] = $dat;
					}

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
			
					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						$scheme = get_scheme( $this->section, ( $datas['scheme'] )? $datas['scheme'] : 'user' );
						$datas['scheme_lvl'] = ( $scheme )? $scheme['scheme_lvl'] : 0;

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
					}

					$datas = $this->json_encoding( $datas );

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $result['succ'] )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];
						}
					}
				break;
				case "delete":
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$datas['id'] = $id;
							$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							$outcome['id'][] = $result['id'];
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "print":
					$this->print_form( $datas['id'] );

					exit;
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
            if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }
        finally
        {
        	if( $succ )
                if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
            else 
                if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}

	public function get_permission_info( $caps_only = false )
	{
		$accessRight = array();
		$rights = array();
		$cap_section = $this->refs['cap_section'];

		$main = apply_filters( 'wcwh_get_i18n', 'major-permission' );
		if( $main )
		{
			foreach( $main as $section => $permission )
			{
				$capabilities = array();
				foreach( $permission as $right => $title )
				{
					$capabilities[ $right ] = ( $title )? $title : "Add ".$right;
					$rights[] = $right;
				}

				$accessRight[ $section ] = array(
					'title' => $cap_section[ $section ],
					'caps'	=> $capabilities,
				);
			}
		}
		
		$permissions = apply_filters( 'wcwh_get_i18n', 'permission' );
		if( $permissions )
		{
			$menu_sect = [];
			foreach( $this->refs['menu'] as $key => $menus )
			{
				foreach( $menus as $menu => $vals )
				{
					$menu_sect[ $menu ] = $vals;
				}
			}

			foreach( $permissions as $section => $args )
			{
				if( $menu_sect[ $section ]['off'] ) continue;

				$capabilities = [];
				if( $args['rights'] )
				{
					foreach( $args['rights'] as $right => $desc )
					{
						$r = $right."_".$section;
						$capabilities[ $r ] = ( $desc )? $desc : "Add ".$r;

						$rights[] = $r;
					}
				}

				$outlet = ( $args['outlet'] )? $args['outlet'] : 0;
				
				$segments = [];
				if( $args['section'] )
				{
					foreach( $args['section'] as $segment => $innest )
					{
						if( $menu_sect[ $segment ]['off'] ) continue;
						
						$capability = [];
						foreach( $innest['rights'] as $right => $desc )
						{
							$r = $right."_".$section;
							$capability[ $r ] = ( $desc )? $desc : "Add ".$r;

							$rights[] = $r;
						}

						$segments[ $segment ] = [
							'title' => $innest['title'],
							'caps' => $capability,
							'outlet' => $innest['outlet'],
						];
					}
				}

				$accessRight[ $section ] = [
					'title' => $args['title'],
					'caps'	=> $capabilities,
					'segments' => $segments,
					'outlet' => $outlet,
				];
			}
		}

		if( $caps_only ) return array_unique( $rights );

		return $accessRight;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Permission" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Permission"
				>
					<?php echo $actions['save'] ?> Permission
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'roles'		=> array_keys( $this->refs['roles'] ),
		);

		$args['permission'] = $this->get_permission_info();
		//print_data( $args['permission'] );

		$sys_roles = get_option( 'wcwh_roles' );
		$sys_roles = maybe_unserialize( $sys_roles );
		
		$plugin_roles = wp_parse_args( $sys_roles,  array_keys( $this->refs['roles'] ) );
		$plugin_roles = array_unique( $plugin_roles );
		if( $plugin_roles ) $args['roles'] = $plugin_roles;
		
		if( $id )
		{
			$datas = $this->Logic->get_infos( [ 'id' => $id ], [], true, [] );
			if( $datas )
			{
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$datas['permission'] = is_json( $datas['permission'] )? json_decode( $datas['permission'], true ) : $datas['permission'];
				
				$args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/permission-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/permission-form.php', $args );
		}
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/permissionListing.php" ); 
			$Inst = new WCWH_Permission_Listing();
			$Inst->set_section_id( $this->section_id );

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			//$count = $this->Logic->count_statuses();
			//if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();

			$datas = $this->Logic->get_infos( $filters, $order );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}