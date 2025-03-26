<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_GoodIssue_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_good_issue";

	public $useFlag;

	public $GIType;

	protected $users;

	public $ref_issue_type;

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
			"do"			=> "DO",
			"issue_type"	=> "Issue Type",
			'ref_doc'		=> "Reference Doc.",
			"client"		=> "Client",
			"vending_machine" => "Vending Machine",
			"doc_date"		=> "Doc Date",
			"post_date"		=> "Post Date",
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

		switch( $this->ref_issue_type )
		{
			case 'own_use':
			case 'other':
				$col[] = 'client';
				$col[] = 'vending_machine';
				$col[] = 'do';
			break;
			case 'vending_machine':
				$col[] = 'client';
			break;
			case 'reprocess':
			case 'transfer_item':
				$col[] = 'client';
				$col[] = 'vending_machine';
				$col[] = 'do';
			break;
			default:
				$col[] = 'vending_machine';
				$col[] = 'do';
			break;
		}

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'docno' => [ "docno", true ],
			'doc_date' => [ "doc_date", true ],
			'post_date' => [ "post_date", true ],
			'ref_doc' => [ "ref_doc", true ],
			'client' => [ "client_company_code", true ],
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
		if( current_user_can( 'post_'.$this->section_id ) )
			$actions['post'] = $this->refs['actions']['post'];
		
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
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
				'post' => [ 'post_'.$this->section_id ],
				'approve' => [ 'approve_'.$this->section_id ],
				'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
				'unpost' => [ 'unpost_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
				//'close' => [ 'close_'.$this->section_id ],
			),
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="col-md-4">
				<label class="" for="flag">By Item</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
	                wcwh_form_field( 'filter[product_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1 
	                    ], 
	                    isset( $this->filters['product_id'] )? $this->filters['product_id'] : '', $view 
	                ); 
				?>
			</div>
			
			<!-- <div class="col-md-4">
				<label class="" for="flag">By Client</label> -->
				<?php
					/*$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_client[]', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ], '' );
	                wcwh_form_field( 'filter[client_company_code][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1 
	                    ], 
	                    isset( $this->filters['client_company_code'] )? $this->filters['client_company_code'] : '', $view 
	                );*/ 
				?>
			<!--</div> -->
		</div>
	<?php
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
			case 'delivery_order':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printDO" 
				 	data-title="Print Delivery Order" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> DO</a>';
			break;
		}

		return $btn;
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
		$this->print_view = true;
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_do( $item )
	{
		$actions = [];
		//$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'delivery_order', 'doc_code'=>'DO', 'title_addon'=>'Delivery Order' ] );
		//$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'delivery_order', 'doc_code'=>'DO c', 'title_addon'=>'Delivery Order by Category', 'view_type'=>'category' ] );

		$actions['do'] = $this->get_action_btn( $item, 'delivery_order' ); 

		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_action( $item )
	{
		$actions = [];
		$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'picking_list', 'doc_code'=>'PL', 'title_addon'=>'Picking List' ] );

		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_post_date( $item )
	{
		return !empty( $item['posting_date'] )? $item['posting_date'] : ( !empty( (int)$item['post_date'] )? $item['post_date'] : '' );
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

	public function column_issue_type( $item )
	{
		return $this->GIType[ $item['good_issue_type'] ];
	}

	public function column_ref_doc( $item )
	{
		$html = $item['ref_doc'];
		if( $item['ref_doc_id'] )
		{
			switch( $item['ref_doc_type'] )
			{
				case 'sale_debit_note':
					$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_sale_cdnote_action', 'title'=>$html, 'permission'=>[ 'access_wh_sale_cdnote' ] ];
					$html = $this->get_external_btn( $html, $args );
				break;
				case 'good_receive':
					$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_good_receive_action', 'title'=>$html, 'permission'=>[ 'access_wh_good_receive' ] ];
					$html = $this->get_external_btn( $html, $args );
				break;
				case 'sale_order':
					$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_sales_order_action', 'title'=>$html, 'permission'=>[ 'access_wh_sales_order' ] ];
					$html = $this->get_external_btn( $html, $args );
				break;
				case 'delivery_order':
					$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_delivery_order_action', 'title'=>$html, 'permission'=>[ 'access_wh_delivery_order' ] ];
					$html = $this->get_external_btn( $html, $args );
				break;
			}
		}
		return $html;
	}
	
	public function column_client( $item )
	{
		if( $item['client_company_code'] )
		{
			$filters = [ 'code'=>$item['client_company_code'] ];
			if( $this->view_outlet && $this->warehouse['id'] ) $filters['seller'] = $this->warehouse['id'];

			$comp = apply_filters( 'wcwh_get_client', $filters, [], true, [] );
		}
		
		$html = ( $comp )? $comp['code'].' - '.$comp['name'] : $item['client_company_code'];
		$args = [ 'id'=>$comp['id'], 'service'=>'wh_client_action', 'title'=>$html, 'permission'=>[ 'access_wh_client' ] ];
		return $this->get_external_btn( $html, $args );
	}

	public function column_vending_machine( $item )
	{
		if( $item['vending_machine'] )
		{
			$filters = [ 'id'=>$item['vending_machine'] ];
			if( $this->view_outlet && $this->warehouse['id'] ) $filters['seller'] = $this->warehouse['id'];

			$dat = apply_filters( 'wcwh_get_vending_machine', $filters, [], true, [] );
		}
		
		$html = ( $dat )? $dat['code'].' - '.$dat['name'] : '';
		$args = [ 'id'=>$dat['id'], 'service'=>'wh_vending_machine_action', 'title'=>$html, 'permission'=>[ 'access_wh_vending_machine' ] ];
		return $this->get_external_btn( $html, $args );
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