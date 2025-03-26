<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_PartsRequest_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_parts_request";

	public $useFlag;

	protected $users;

	protected $outlet_permission = ['view', 'update_api'];

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
			//"tr" 			=> "Print Form",
			"doc_date"		=> "Doc Date",
			"receiver_name"	=> "Receiver",
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
		if( current_user_cans( [ 'save_'.$this->section_id ] ) )
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
		return array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				//$temp => [ 'wh_admin_support' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
				'post' => [ 'post_'.$this->section_id ],
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_support' ],
				'unpost' => [ 'unpost_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_support' ],
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
			case 'print_form':
				if( current_user_cans( [ 'save_'.$this->section_id ] ) )
				{
					$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printTR" 
				 	data-title="Print Form" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> Form</a>';
				}
			break;
		}

		return $btn;
	}
	
	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Item</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$filter['grp_id'] = $this->setting[ $this->section_id ]['used_item_group'];
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
	                wcwh_form_field( 'filter[product_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1 
	                    ], 
	                    isset( $this->filters['product_id'] )? $this->filters['product_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Receiver</label>
				<?php
					$not_acc_type = $this->setting['wh_customer']['non_editable_by_acc_type'];

					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					//if( $not_acc_type ) $filter['not_acc_type'] = $not_acc_type;
					$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '' );
                
	                wcwh_form_field( 'filter[customer_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['customer_id'] )? $this->filters['customer_id'] : '', $view 
	                ); 
				?>
			</div>
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
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['doc_id'], $item['doc_id'] );
		
		return $html;
    }

	public function column_docno( $item ) 
	{	
		if( ! current_user_cans( ['wh_admin_support'] ) && in_array( $item['status'], [ '6' ] ) )
		{
			$fqty = get_document_meta( $item['doc_id'], 'fulfill_qty', '> 0' );
			$fqty = array_sum( $fqty );
			if( $fqty > 0 ) $item['status'] = 9;
		}
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_tr( $item )
	{
		$actions = [];
		if( $item['status'] > 1 )
		$actions['tr'] = $this->get_action_btn( $item, 'print_form', ['force'=>true]); 
	
		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_post_date( $item )
	{
		return !empty( $item['posting_date'] )? $item['posting_date'] : ( !empty( (int)$item['post_date'] )? $item['post_date'] : '' );
	}

	public function column_receiver_name( $item )
	{		
		if( $item['customer_id'] )
		{
			$filter = [ 'id'=>$item['customer_id'] ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
			$data = apply_filters( 'wcwh_get_customer', $filter, [], true);
			if( $data ) $item['receiver_name'] = $data['uid'].", ".$data['code'].", ".$data['name'];
		}

		if(!$item['receiver_name'])
		{
			$employee = apply_filters( 'wcwh_get_customer_all', $filters, [], false, ['account'=>1] );
			$curr_receiver = [];
			foreach ($employee as $key => $value) 
			{
				if($value['uid'] == $item['customer_uid'])
				{
					$item['receiver_name'] =$value['uid'].", ".$value['code'].", ".$value['name'];
					break;
				}
				
			}
		}
		return $item['receiver_name'];
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