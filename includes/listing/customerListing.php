<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_Customer_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_customer";

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
			"serial"		=> "Customer No.",
			"print"			=> "Print ID",
			"receipt_count"	=> "Receipt",
			"sapuid" 		=> "SAP No.",
			"acc_type"		=> "Account Type",
			"cjob_id"		=> "Job / Position",
			"origin"		=> "Origin",
			"cgroup_id"		=> "Group",
			"parent"		=> "Superior",
			"sapuid_date" 	=> "SAP No. Date",
			"comp_id"		=> "Company",
			"wh_code" 		=> "Warehouse",
			"email"			=> "Email",
			"phone_no"		=> "Phone No.",
			"last_day"		=> "Last Day",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
		);
	}

	public function get_hidden_column()
	{
		$col = array( 'email', 'phone_no', 'wh_code', 'comp_id', 'sapuid_date', 'parent' );
		if( ! $this->useFlag ) $col[] = 'approval';

		if( ! current_user_cans( [ 'save_wh_credit' ] ) )
			$col[] = 'cgroup_id';

		if( ! current_user_cans( [ 'print_id_wh_customer' ] ) )
			$col[] = 'print';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'name' => [ "name", true ],
			'serial' => [ "serial", true ],
			'receipt_count' => [ "receipt_count", true ],
			'sapuid' => [ "sapuid", true ],
			'acc_type' => [ "acc_type", true ],
			'cjob_id' => [ "cjob_code", true ],
			'cgroup_id' => [ "cgroup_code", true ],
			'created' => [ "created_at", true ],
			'lupdate' => [ "lupdate_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		//if( current_user_can( 'delete_'.$this->section_id ) )
		//	$actions['delete'] = $this->refs['actions']['delete'];
		if( current_user_cans( [ 'print_id_wh_customer' ] ) )
			$actions['print'] = $this->refs['actions']['print'];
		
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
				'restore' => [ 'restore_'.$this->section_id ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
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
			case 'print':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printCustomer" 
				 	data-title="Print Staff ID" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> Staff ID</a>';
			break;
		}

		return $btn;
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Customer</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '' );
                
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
					$options = options_data( apply_filters( 'wcwh_get_account_type', $filter, [], false, [] ), 'id', [ 'code' ], '' );
                
	                wcwh_form_field( 'filter[acc_type][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['acc_type'] )? $this->filters['acc_type'] : '', $view 
	                ); 
				?>
			</div>
			
			<div class="segment col-md-4">
				<label class="" for="flag">By Job / Position</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_customer_job', $filter, [], false, [] ), 'id', [ 'name' ] );
                
	                wcwh_form_field( 'filter[cjob_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['cjob_id'] )? $this->filters['cjob_id'] : '', $view 
	                ); 
				?>
			</div>
			
			<div class="segment col-md-4">
				<label class="" for="flag">By Origin</label><br>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_origin_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
		            wcwh_form_field( 'filter[origin]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['origin'] )? $this->filters['origin'] : '', $view 
		            ); 
				?>
			</div>
			
			<?php //if( current_user_cans( [ 'assign_group_wh_customer' ] ) ): ?>
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
			<?php //endif; ?>

			<div class="segment col-md-4">
				<label class="" for="flag">Have Photo</label><br>
				<?php
					$options = [ ''=>'All', 'yes'=>'Yes', 'no'=>'No' ];
                
		            wcwh_form_field( 'filter[photo]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
		                    'options'=> $options
		                ], 
		                isset( $this->filters['photo'] )? $this->filters['photo'] : '', $view 
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

	public function column_cgroup_id( $item )
	{
		$html = $item['cgroup_code']." - ".$item['cgroup_name'];
		$args = [ 'id'=>$item['cgroup_id'], 'service'=>'wh_customer_group_action', 'title'=>$html, 'permission'=>[ 'access_wh_customer_group' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_cjob_id( $item )
	{
		$html = $item['cjob_code']." - ".$item['cjob_name'];
		$args = [ 'id'=>$item['cjob_id'], 'service'=>'wh_customer_job_action', 'title'=>$html, 'permission'=>[ 'access_wh_customer_job' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_origin( $item )
	{
		$html = $item['origin_code'];
		$args = [ 'id'=>$item['origin'], 'service'=>'wh_origin_group_action', 'title'=>$html, 'permission'=>[ 'access_wh_origin_group' ] ];
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
		if( $item['status'] > 0 && current_user_cans( [ 'new_no_wh_customer' ] ) )
		{
			$actions['new-serial'] = $this->get_action_btn( $item, 'new-serial' );
		}

		$datas = [ 'dataname'=>$item['name'], 'dataserial'=>$item['serial'] ];
		
		if( current_user_cans( [ 'print_qr_wh_customer' ] ) )
		$actions['qr'] = $this->get_action_btn( $item, 'qr', [ 'serial'=>$item['serial'], 'tpl'=>'customer_label', 'datas'=>$datas, 'force'=>1 ] );
		if( current_user_cans( [ 'print_bar_wh_customer' ] ) )
		$actions['barcode'] = $this->get_action_btn( $item, 'barcode', [ 'serial'=>$item['serial'], 'tpl'=>'customer_label', 'datas'=>$datas, 'force'=>1 ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['serial'].'</strong>', $this->row_actions( $actions, true ) ); 
	}

	public function column_print( $item )
	{
		$actions = [];
		$actions['print'] = $this->get_action_btn( $item, 'print', ['force'=>true]); 
	
		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_sapuid( $item )
	{
		return $item['uid'];
	}

	public function column_sapuid_date( $item )
	{
		return get_customer_meta( $item['id'], 'sapuid_date', true );
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