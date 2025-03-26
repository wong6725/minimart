<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Permission_Class" ) ) include_once( WCWH_DIR . "/includes/classes/permission.php" ); 

if ( !class_exists( "WCWH_Role_Controller" ) ) 
{

class WCWH_Role_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_role";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Role_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newRole',
	);

	protected $def_roles = [
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
		'cashier',
		'pos_manager',
		'customer',
		'shop_manager',
		'storekeeper',
		'client',
	];

	protected $section = 'role';

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

					if( ! $datas['role'] ) $datas['role'] = $datas['id'];
					
					$datas['role'] = !empty( $datas['role'] )? sanitize_title( $datas['role'] ) : sanitize_title( $datas['name'] );
					$datas['role'] = str_replace( '-', '_', $datas['role'] );

					$succ = $this->create_role( $datas['role'], $datas['name'] );
			
					if( $succ && ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						$permission = wp_parse_args( [ 'ref_id'=>$datas['role'] ], $this->get_defaultFields() );

						$result = $this->Logic->action_handler( 'save', $permission, $metas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}
					}
				break;
				case "delete":
					$role = $datas['id'];

					if( $role )
					{
						$succ = $this->delete_role( $role );
						if( $succ )
						{
							$permission = $this->Logic->get_infos( [ 'scheme'=>'role', 'ref_id'=>$role ], [], true );
							if( $permission )
							{
								$d = [ 'id'=>$permission['id'] ];
								$result = $this->Logic->action_handler( 'delete', $d, $metas, $obj );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	if( $succ )
           	{
           		$roles = $this->refs['roles'];
           		if( $roles )
           		{
					$def = [];
           			foreach( $roles as $role => $infos )
					{
						if( ! $infos['capable'] ) continue;

						$def[] = $role;
					}

					global $wp_roles;
					$r = array_merge( $def, $this->def_roles );
					$r = array_unique( $r );
					foreach( $wp_roles->roles as $role => $vals )
					{
						if( ! in_array( $role, $r ) )
						{
							$def[] = $role;
						}
					}
					
    				update_option( 'wcwh_roles', maybe_serialize( $def ) );
           		}
           	}

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
					data-title="<?php echo $actions['save'] ?> Role" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Role"
				>
					<?php echo $actions['save'] ?> Role
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
		);
		
		if( $id )
		{
			$datas = $this->get_roles( $id );
			if( $datas )
			{
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/role-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/role-form.php', $args );
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
			include_once( WCWH_DIR."/includes/listing/roleListing.php" ); 
			$Inst = new WCWH_Role_Listing();
			$Inst->set_section_id( $this->section_id );

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			$Inst->def_roles = $this->def_roles;
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$datas = $this->get_roles();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_roles( $role = '' )
	{
		global $wp_roles;

    	$roles = $wp_roles->roles;
    	
    	$r = [];
    	if( $roles )
    	{
    		$i = 0;
    		foreach( $roles as $key => $vals )
    		{
    			if( $role && $role == $key )
    			{
    				$vals['role'] = $key;
    				$vals['id'] = $key;
    				return $vals;
    			}

    			$r[$i] = $vals;
    			$r[$i]['role'] = $key;
    			$r[$i]['id'] = $key;

    			$i++;
    		}
    	}

    	return $r;
	}

	public function create_role( $role = '', $name = '' )
	{
		if( ! $role || ! $name ) return false;

		global $wp_roles;

		if( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();
		$copy = $wp_roles->get_role( 'editor' );

		add_role( $role, $name, $copy->capabilities );

		return true;
	}

	public function delete_role( $role = '' )
	{
		if( ! $role ) return false;

		global $wp_roles;

		remove_role( $role );

		return true;
	}
	
} //class

}