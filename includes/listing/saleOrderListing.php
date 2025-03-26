<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_SaleOrder_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_sales_order";

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
			"docno" 		=> "Doc No.",
			"pl"			=> "PL",
			"inv"			=> "INV",
			//"pr"			=> "PR No.",
			"sap_po"		=> "SAP PO",
			"client"		=> "Client",
			"doc_date"		=> "Doc Date",
			"post_date"		=> "Post Date",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"direct_issue"	=> "DIssue",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			"remark"		=> "Remark",
		);
	}

	public function get_hidden_column()
	{
		$col = [];
		if( ! $this->useFlag ) $col[] = 'approval';

		if( ! current_user_cans( ['wh_admin_support'] ) )
			$col[] = 'direct_issue';

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'docno' => [ "docno", true ],
			'doc_date' => [ "doc_date", true ],
			'post_date' => [ "post_date", true ],
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
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'unpost' => [ 'unpost_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'incomplete' => [ 'incomplete_'.$this->section_id ],
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
			<div class="col-md-4">
				<label class="" for="flag">By Client</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [ 'usage'=>0 ] ), 'code', [ 'code', 'name', 'status_name' ], '' );
					
	                wcwh_form_field( 'filter[client_company_code][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1 
	                    ], 
	                    isset( $this->filters['client_company_code'] )? $this->filters['client_company_code'] : '', $view 
	                ); 
				?>
			</div>
			<?php if( current_user_cans( ['wh_admin_support'] ) ): ?>
			<div class="col-md-4">
				<label class="" for="flag">By Direct Issue</label>
				<?php
					$options = [ ''=>'All', '1'=>'YES' ];
					
	                wcwh_form_field( 'filter[direct_issue]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options 
	                    ], 
	                    isset( $this->filters['direct_issue'] )? $this->filters['direct_issue'] : '', $view 
	                ); 
				?>
			</div>
		<?php endif; ?>
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
			case 'picking_list':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printPL" 
				 	data-title="Print Picking List" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> PL</a>';
			break;
			case 'invoice':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printINV" 
				 	data-title="Print Invoice" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> INV</a>';
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

	public function column_pl( $item )
	{
		$actions = [];
		if( $item['status'] == 6 )
		{
			//	$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'picking_list', 'doc_code'=>'PL', 'title_addon'=>'Picking List' ] );
			$actions['picking_list'] = $this->get_action_btn( $item, 'picking_list' ); 
		}	

		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_inv( $item )
	{
		$actions = [];
		if( $item['status'] > 0 )
		{
			//$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'invoice', 'doc_code'=>'INV', 'title_addon'=>'Invoice' ] );
			//$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'invoice', 'doc_code'=>'INV c', 'title_addon'=>'Invoice by Category', 'view_type'=>'category' ] );

			$actions['invoice'] = $this->get_action_btn( $item, 'invoice' ); 
		}

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
	
	public function column_pr( $item )
	{	
		$html = $item['purchase_doc'];
		if( $item['ref_doc_id'] && $item['ref_doc_type'] == 'purchase_request' )
		{
			$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_purchase_request_action', 'title'=>$html, 'permission'=>[ 'access_wh_purchase_request' ] ];
			$html = $this->get_external_btn( $html, $args );
		}
		return $html;
	}

	public function column_direct_issue( $item )
	{
		return ( $item['direct_issue'] )? 'YES' : 'NO';
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