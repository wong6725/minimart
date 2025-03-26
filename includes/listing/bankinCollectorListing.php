<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_BankInCollector_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_bankin_collector";

	public $useFlag;

	protected $users;

	protected $outlet_permission = ['view'];

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
			"docno" 		=> "Doc No.",
			"bic" 			=> "Collection Form",
			"doc_date"		=> "Doc Date",
			"from_docno"	=> "From Doc No.",
			"to_docno"		=> "To Doc No.",
			"order_count"	=> "Total Receipts",
			"total_amount"	=> "Total Amount (RM)",
			//"service_charge"=> "Service Charge (RM)",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			"remark"		=> "Remark",
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
			'docno' => [ "docno", true ],
			'doc_date' => [ "doc_date", true ],
			'created' => [ "created_at", true ],
			'lupdate' => [ "lupdate_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'post_'.$this->section_id ) )
			$actions['post'] = $this->refs['actions']['post'];
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
		if($this->view_outlet && $this->outlet_permission) $temp = 'update_api';
		else $temp = 'edit';
	
		return array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				//$temp => [ 'wh_admin_support' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				$temp => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
				'post' => [ 'post_'.$this->section_id ],
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				$temp => [ 'wh_admin_support' ],
				'unpost' => [ 'unpost_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				//$temp => [ 'wh_admin_support' ],
				//'close' => [ 'close_'.$this->section_id ],
			),
		);
	}
	
	public function action_btn_addon( $btn, $item, $action = "view", $args = array() )
	{
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['docno'];
		$id = ( $args['id'] )? $args['id'] : ( ( $item['doc_id'] )? $item['doc_id'] : $item['id'] );
		
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
			case 'bank_in':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printBIC" 
				 	data-title="Print Form" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> Form</a>';
			break;
		}

		return $btn;
	}

	/**
	 * 	Overriding Action Btn
	 * 	--------------------------------------------------------------------------------------------- 
	 */

	public function get_actions( $item, $status = 'default', $args = array() )
	{
		$status_actions = $this->get_status_action( $item );
		$actions = [];

		if( $status_actions && $status_actions[ (string) $status ] && ! $this->view_outlet )
		{
			foreach( $status_actions[ (string) $status ] as $action => $permission )
			{	
				if( empty( $permission ) || ( $permission && current_user_cans( $permission ) ) )
				{
					$actions[] = $this->get_action_btn( $item, $action, $args );
				}
			}
		}
		//----------------test jeff------------------------//
		else if( $status_actions && $status_actions[ (string) $status ] && $this->view_outlet && $this->outlet_permission )
		{
			foreach( $status_actions[ (string) $status ] as $action => $permission )
			{	
				if( empty( $permission ) || ( $permission && current_user_cans( $permission ) ) )
				{
					if( in_array($action, $this->outlet_permission) )
						$actions[] = $this->get_action_btn( $item, $action, $args );
				}
			}
		}
		//----------------test jeff------------------------//
		else
		{
			$actions[] = $this->get_action_btn( $item, 'view', $args );
		}
		
		return $actions;
	}
	
	public function filter_search()
	{
	?>
		<div class="row">
			
		</div>
		<div class="row">
			<?php
				wcwh_form_field( 'section', 
	                [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[], ], 
	                $this->section_id, $view 
	            ); 
			?>
		</div>
	<?php
	}


	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		if( $item['status'] > 0 )
			$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['doc_id'], $item['doc_id'] );
		
		return $html;
    }

	public function column_docno( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_bic( $item )
	{
		$actions = [];
		if( $item['status'] >= 6 )
		$actions['bis'] = $this->get_action_btn( $item, 'bank_in', ['force'=>true]); 
	
		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_post_date( $item )
	{
		return !empty( $item['posting_date'] )? $item['posting_date'] : ( !empty( (int)$item['post_date'] )? $item['post_date'] : '' );
	}

	public function column_order_count( $item )
	{
		return "<strong class='font14'>".round_to( $item['order_count'], 0, 1, 1 )."</strong>";
	}

	public function column_total_amount( $item )
	{
		return "<strong class='font14'>".round_to( $item['total_amount'], 2, 1, 1 )."</strong>";
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

	public function column_created( $item )
	{
		$user = ( $this->users )? $this->users[ $item['created_by'] ] : $item['created_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['created_at'];

		$html = $user.'<br>'.$date;
		
		$args = [ 
			'action' => 'view_doc', 
			'id' => $item['doc_id'], 
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
			'id' => $item['doc_id'], 
			'service' => 'wh_logs_action', 
			'title' => $html, 
			'desc' => 'View History',
			'permission' => [ 'wcwh_user' ] 
		];
		return $this->get_external_btn( $html, $args );
	}
	
} //class