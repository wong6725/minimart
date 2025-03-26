<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TransferItemGR_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_transfer_item";

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
			"do"			=> "DO / DN No.",
			"invoice"		=> "Invoice",
			"po"			=> "PO / PR No.",
			"supplier"		=> "Supplier",
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

		return $col;
	}

	public function get_sortable_columns()
	{
		$col = [
			'docno' => [ "docno", true ],
			'do' => [ "delivery_doc", true ],
			'invoice' => [ "invoice", true ],
			'po' => [ "purchase_doc", true ],
			'supplier' => [ "supplier_company_code", true ],
			'doc_date' => [ "doc_date", true ],
			'post_date' => [ "post_date", true ],
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
		$actions = [];

		return $actions;
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
				<label class="" for="flag">By Supplier</label>
				<?php
					$filter = [];
					if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
					$options = options_data( apply_filters( 'wcwh_get_supplier', $filter, [], false, [ 'usage'=>0, 'company'=>1 ] ), 'code', [ 'code', 'name', 'comp_code', 'status_name' ], '' );
	                wcwh_form_field( 'filter[supplier_company_code][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1 
	                    ], 
	                    isset( $this->filters['supplier_company_code'] )? $this->filters['supplier_company_code'] : '', $view 
	                ); 
				?>
			</div>
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
			case 'transfer_item':
				$btn = '<button class="linkAction btn btn-xs btn-info btn-none-'.$action.'" title="'.$actions[ $action ].'"
				 	data-title="'.$actions[ $action ].'" data-action="transfer_item_reference" 
					data-service="'.$this->section_id.'_action" data-id="'.$id.'"
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-message="Transfer Item"
					>Transfer Item</button>';
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
		$actions = [];
		$actions['view'] = $this->get_action_btn( $item, 'view', [ 'services'=>'wh_good_receive_action' ] );
		
		if( ! $this->view_outlet && $item['status'] > 0 )
			$actions['transfer_item'] = $this->get_action_btn( $item, 'transfer_item' ); 
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
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

	public function column_do( $item )
	{	
		$html = $item['delivery_doc'];
		if( $item['ref_doc_id'] && $item['ref_doc_type'] == 'delivery_order' )
		{
			$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_delivery_order_action', 'title'=>$html, 'permission'=>[ 'access_wh_delivery_order' ] ];
			$html = $this->get_external_btn( $html, $args );
		}
		return $html;
	}

	public function column_po( $item )
	{
		$html = $item['purchase_doc'];
		if( $item['purchase_request_doc_id'] )
		{
			$args = [ 'id'=>$item['purchase_request_doc_id'], 'service'=>'wh_purchase_request_action', 'title'=>$html, 'permission'=>[ 'access_wh_purchase_request' ] ];
			$html = $this->get_external_btn( $html, $args );
		}
		if( $item['ref_doc_id'] )
		{
			$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_purchase_order_action', 'title'=>$html, 'permission'=>[ 'access_wh_purchase_order' ] ];
			$html = $this->get_external_btn( $html, $args );
		}
		return $html;
	}
	
	public function column_supplier( $item )
	{	
		if( $item['supplier_company_code'] )
		{
			$filters = [ 'code'=>$item['supplier_company_code'] ];
			if( $this->view_outlet && $this->warehouse['id'] ) $filters['seller'] = $this->warehouse['id'];
			
			$supplier = apply_filters( 'wcwh_get_supplier', $filters, [], true, [] );
		}
		
		$html = ( $supplier )? $supplier['code'].' - '.$supplier['name'] : $item['supplier_company_code'];
		$args = [ 'id'=>$supplier['id'], 'service'=>'wh_supplier_action', 'title'=>$html, 'permission'=>[ 'access_wh_supplier' ] ];
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