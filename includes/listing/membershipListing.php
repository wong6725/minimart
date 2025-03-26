<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Membership_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_membership";

	public $useFlag;

	protected $users;

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
			"name" 			=> "Name",
			"serial"		=> "Membership No.",
			"code"			=> "Customer Code",
			"uid" 			=> "SAP No.",
			"acc_type"		=> "Account Type",
			"cgroup_id"		=> "Group",
			"email"			=> "Email",
			"phone_no"		=> "Phone No.",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'email', 'phone_no' );
		if( ! $this->useFlag ) $col[] = 'approval';

		if( ! current_user_cans( [ 'save_wh_credit' ] ) )
			$col[] = 'cgroup_id';

		if( ! current_user_cans( [ 'print_id_wh_membership' ] ) )
			$col[] = 'serial';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'name' => [ "name", true ],
			'acc_type' => [ "acc_type", true ],
			'cgroup_id' => [ "cgroup_code", true ],
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

	public function get_status_action( $item )
	{
		$actions = array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
				//'restore' => [ 'restore_'.$this->section_id ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				//'delete' => [ 'delete_'.$this->section_id ],
			),
		);

		if( ! current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) && 
			$this->setting[ $this->section_id ]['non_editable_by_acc_type'] && 
			in_array( $item['acc_type'], $this->setting[ $this->section_id ]['non_editable_by_acc_type'] )
		)
		{
			unset( $actions['1']['edit'] );
			unset( $actions['1']['delete'] );
		}

		return $actions;
	}

	public function action_btn_addon( $btn, $item, $action = "view", $args = array() )
	{
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['name'];
		$id = ( $args['id'] )? $args['id'] : ( ( $item['doc_id'] )? $item['doc_id'] : $item['id'] );
		$serial = ( $args['serial'] )? $args['serial'] : $item['serial'];
		
		$attrs = array();
		$html_attr = "";
		if( !empty( $args['datas'] ) )
		{
			foreach( $args['datas'] as $key => $value )
			{
				$attrs[] = "data-{$key}='{$value}'";
			}
			if( $attrs )
			{
				$html_attr = implode( " ", $attrs );
			}
		}
		
		switch( $action )
		{
			case 'new-serial':
				$btn = '<a class="linkAction btn btn-xs btn-info btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$item['id'].'" data-action="'.$action.'" data-service="'.$this->section_id.'_action"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'"
					data-message="Request new No. for '.$item['name'].'?"
					>New No.</a>';
			break;
			case 'reset-pin':
				$btn = '<a class="linkAction btn btn-xs btn-info btn-none-'.$action.'" title="'.$actions[ $action ].'" 
					data-id="'.$item['id'].'" data-action="'.$action.'" data-service="'.$this->section_id.'_action"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'"
					data-message="Reset Pin for '.$item['name'].'?"
					>Reset Pin</a>';
			break;
		}

		return $btn;
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Membership</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_membership', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['id'] )? $this->filters['id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Account Type</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_account_type', $filter, [], false, [] ), 'id', [ 'code' ] );
                
	                wcwh_form_field( 'filter[acc_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['acc_type'] )? $this->filters['acc_type'] : '', $view 
	                ); 
				?>
			</div>
			
			<?php if( current_user_cans( [ 'save_wh_credit' ] ) ): ?>
			<div class="segment col-md-4">
				<label class="" for="flag">By Credit Group</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_customer_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'name' ] );
                
		            wcwh_form_field( 'filter[cgroup_id]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['cgroup_id'] )? $this->filters['cgroup_id'] : '', $view 
		            ); 
				?>
			</div>
			<?php endif; ?>
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

		if( $item['status'] > 0 && current_user_cans( [ 'reset_pin_wh_membership' ] ) )
		{
			$actions['reset-pin'] = $this->get_action_btn( $item, 'reset-pin', [ 'force'=>1 ] );
		}
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['name'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_cgroup_id( $item )
	{
		$html = $item['cgroup_code']." - ".$item['cgroup_name'];
		$args = [ 'id'=>$item['cgroup_id'], 'service'=>'wh_customer_group_action', 'title'=>$html, 'permission'=>[ 'access_wh_customer_group' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_acc_type( $item )
	{
		$html = $item['acc_code'];
		$args = [ 'id'=>$item['acc_type'], 'service'=>'wh_account_type_action', 'title'=>$html, 'permission'=>[ 'access_wh_account_type' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_serial( $item )
	{
		$actions = array();
		if( $item['status'] > 0 && current_user_cans( [ 'new_no_wh_membership' ] ) )
		{
			$actions['new-serial'] = $this->get_action_btn( $item, 'new-serial', [ 'force'=>1 ] );
		}

		$datas = [ 'dataname'=>$item['name'], 'dataserial'=>$item['serial'] ];

		if( $item['wh_code'] ) 
		{
			$estate = explode( "-", $item['wh_code'] );
			$datas[ 'dataestate' ] = $estate[1];
		}
		
		if( current_user_cans( [ 'print_qr_wh_membership' ] ) )
		$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$item['serial'], 'tpl'=>'customer_label', 'datas'=>$datas, 'force'=>1 ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['serial'].'</strong>', $this->row_actions( $actions, true ) ); 
	}

	public function column_print( $item )
	{
		$actions = [];
		$actions['print'] = $this->get_action_btn( $item, 'print', ['force'=>true]); 
	
		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['customer_status'] ]['key']}'>{$statuses[ $item['customer_status'] ]['title']}</span>";

		return $html;
	}

	public function column_approval( $item )
	{
		$approval = $this->get_approvals();
		$html = "<span class='list-stat list-{$approval[ $item['flag'] ]['key']}'>{$approval[ $item['flag'] ]['title']}</span>";

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