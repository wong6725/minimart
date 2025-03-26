<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_User_Class" ) ) include_once( WCWH_DIR . "/includes/classes/user.php" ); 

if ( !class_exists( "WCWH_User_Controller" ) ) 
{

class WCWH_User_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_maintain_user";

	public $Notices;
	public $className = "User_Controller";

	public $Logic;
	public $Customer;
	public $Item;

	public $tplName = array(
		'new' => 'newUser',
		'row' => 'rowUser',
	);

	protected $excl_roles = [
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

	protected $usable_roles = [];

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_User_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->set_excl_roles( $this->excl_roles );

		$this->set_usable_roles( $this->get_usable_role() );
		$this->Logic->set_usable_roles( $this->usable_roles );
	}

	public function set_usable_roles( $roles )
	{
		$this->usable_roles = $roles;
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function get_usable_role()
	{
		$roles = $this->Logic->get_roles();
		
		$usable_roles = [];
		if( $roles )
		{
			foreach( $roles as $i => $role )
			{
				if( ! in_array( $role['role'], $this->excl_roles ) )
					$usable_roles[ $role['role'] ] = $role['name'];
			}
		}

		return $usable_roles;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
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

		if( ! $action || $action < 0 )
		{
			$succ = false;
			$this->Notices->set_notice( 'invalid-action', 'warning' );
		}

		if( ! $datas )
		{
			$succ = false;
			$this->Notices->set_notice( 'insufficient-data', 'warning' );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch( $action )
			{
				case 'save':
					if( ! $datas['new_password'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'Password Required', 'warning' );
					}
					//----------------04/10/2022
					if( $datas['start_date'] && $datas['end_date'] )
					{
						if($datas['end_date'] < $datas['start_date'])
						{
							$succ = false;
							$this->Notices->set_notice( 'End Date should greater than Start Date ', 'warning' );
						}
					}
					//----------------04/10/2022
				break;
				case 'update':
					if( ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( !empty( $datas['new_password'] ) )
					{
						if( ! $datas['confirm_password'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Password Required', 'warning' );
						}
						if( $succ && $datas['new_password'] != $datas['confirm_password'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'Password Confirmation Not Identical', 'warning' );
						}
					}
					//----------------04/10/2022
					if( $datas['start_date'] && $datas['end_date'] )
					{
						if($datas['end_date'] < $datas['start_date'])
						{
							$succ = false;
							$this->Notices->set_notice( 'End Date should greater than Start Date ', 'warning' );
						}
					}
					//----------------04/10/2022
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
					$exist = [];
					if( !empty( $datas['id'] ) )
					{
						$filter = [ 'id'=>$datas['id'] ];
						$exist = $this->Logic->get_infos( $filter, [], true, [] );
					}

					$username = $datas['user_login'];
					$password = $datas['new_password'];
					$email = $datas['user_email'];
					$role = $datas['role'];
					$role = ( in_array( 'norole', $datas['role'] ) )? [] : $datas['role'];

					$user_data = [];
					$user_meta = [];

					if( !empty( $datas['user_login'] ) ) $user_data['user_login'] = $datas['user_login'];
					if( !empty( $datas['nickname'] ) ) $user_data['user_nicename'] = $datas['nickname'];
					if( !empty( $datas['user_email'] ) ) $user_data['user_email'] = $datas['user_email'];
					if( !empty( $datas['display_name'] ) ) $user_data['display_name'] = $datas['display_name'];
					
					if( !empty( $datas['display_name'] ) ) $user_meta['first_name'] = $datas['display_name'];
					if( !empty( $datas['nickname'] ) ) $user_meta['nickname'] = $datas['nickname'];
					$user_meta['outlet'] = !empty( $datas['outlet'] )? $datas['outlet'] : '';
					$user_meta['discount'] = !empty( $datas['discount'] )? $datas['discount'] : 'disable';
					$user_meta['disable_pos_payment'] = !empty( $datas['disable_pos_payment'] )? $datas['disable_pos_payment'] : 'no';
					$user_meta['approve_refunds'] = !empty( $datas['approve_refunds'] )? $datas['approve_refunds'] : 'no';
					$user_meta['user_card_number'] = '';
					
					//----------------04/10/2022
					$user_meta['start_date'] = !empty( $datas['start_date'] )? $datas['start_date'] : '';
					$user_meta['end_date'] = !empty( $datas['end_date'] )? $datas['end_date'] : '';;
					//----------------04/10/2022
					
					if( ! $exist )	//save
					{
						if( null == username_exists( $email_address ) )
						{
							//$password = !empty( $password )? $password : wp_generate_password( 8, false );

							$user_id = wp_create_user( $username, $password, $email );
							if( $user_id )
							{
								$user_data['ID'] = $user_id;
								wp_update_user( $user_data );

								$user = new WP_User( $user_id );
								
								if( $user->roles )
									foreach ( $user->roles as $_role )
										$user->remove_role( $_role );
								
								if( $role )
									foreach( $role as $_role )
										$user->add_role( $_role );

								if( $user_meta )
								{
									foreach( $user_meta as $key => $value )
									{
										update_user_meta( $user_id, $key, $value );
									}
								}
							}
							else
							{
								$succ = false;
								$this->Notices->set_notice( 'Action Failed', 'error' );
							}
						}
						else
						{
							$succ = false;
							$this->Notices->set_notice( 'Username Exists!', 'error' );
						}
					}
					else //update
					{
						$user_id = $datas['id'];
						$user_data['ID'] = $user_id;
						wp_update_user( $user_data );

						if( !empty( $password ) ) wp_set_password( $password, $user_id );

						$user = new WP_User( $user_id );
						
						if( $user->roles )
							foreach ( $user->roles as $_role )
								$user->remove_role( $_role );

						if( $role )
							foreach( $role as $_role )
								$user->add_role( $_role );

						if( $user_meta )
						{
							foreach( $user_meta as $key => $value )
							{
								update_user_meta( $user_id, $key, $value );
							}
						}
					}

					if( !empty( trim( $datas['password_hash'] ) ) )
					{
						$user_obj = get_userdata( $user_id );
						
						if( $user_obj->user_pass !== trim( $datas['password_hash'] ) )
						{
							global $wpdb;
							$this->prefix = $wpdb->prefix;
							$this->tbl = 'users';
							$this->primary_key = 'ID';
							$this->update( $user_id, [ 'user_pass'=>trim( $datas['password_hash'] ) ] );
						}
					}

					if( $succ )
					{
						$outcome['id'][] = $user_id;
						//$outcome['data'][] = $result['data'];
					}
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

	public function after_action( $succ, $id, $action = "save" )
	{
		if( ! $id ) return $succ;

		return $succ;
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
				if( current_user_cans( [ $this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> User" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> User"
				>
					<?php echo $actions['save'] ?> User
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'usable_roles' => $this->usable_roles,
		);

		if( $id )
		{
			$filters = [ 'id' => $id ];
			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			if( $datas )
			{
				$metas = get_user_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/user-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/user-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/userListing.php" ); 
			$Inst = new WCWH_User_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->set_usable_roles( $this->usable_roles );
			//$Inst->set_args( [ 'per_page_row'=>50 ] );

			$Inst->styles = [];

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			if( empty( $filters['role'] ) && !empty( $this->usable_roles ) ) 
			{
				$filters['role'] = array_keys( $this->usable_roles );
				$filters['role'][] = 'no_role';
			}
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, ['stat'=>1], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}