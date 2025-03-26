<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_StockTake_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_stocktake";

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
			"action"		=> "Action",
			"variance"		=> "Variance",
			"doc_date"		=> "Doc Date",
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
				'confirm' => [ 'post_'.$this->section_id ],
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'3' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'unconfirm' => [ 'unpost_'.$this->section_id ],
				'post' => [ 'post_'.$this->section_id ],
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				//'edit' => [ 'wh_admin_support' ],
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

	public function _filter_search()
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
		</div>
	<?php
	}

	public function action_btn_addon( $btn, $item, $action = "view", $args = array() )
	{
		$icons = $this->get_icons();
		$actions = $this->refs['actions'];
		$services = ( $args['services'] )? : $this->section_id.'_action';
		$title = ( $args['title'] )? $args['title'] : $item['name'];
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
			case 'print':
				$print_type = $item['print_type'];
				$print_type = ( $args['print_type'] )? $args['print_type'] : $print_type;

				$view_type = $args['view_type'];

				$prnt_title = $item['doc_code'];
				$prnt_title = ( $args['doc_code'] )? $args['doc_code'] : $prnt_title;

				$title = $actions[ $action ];
				$title.= ( $args['title_addon'] )? ' '.$args['title_addon'] : '';

				$vals = [ 'action'=>$action, 'id'=>$id, 'section'=>$this->section_id, 'type'=>$print_type, 'status'=>1 ];
				if( $view_type ) $vals['view_type'] = $view_type;

				$btn = '<a class="toolTip btn btn-xs btn-info" title="'.$title.'" target="_blank"
					href="'.wcwh_href( $vals ).'"
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i> '.$prnt_title.' </a>';
			break;
			case 'export-stocktake':
				$action = 'export';
				$title = 'Export Stocktake List';

				$args = [ 
					'action' => 'export', 
					'id' => $id, 
					'section' => $this->section_id, 
					'status' => 1,
				];

				$btn = '<a class="toolTip btn btn-xs btn-info" title="'.$title.'" 
					href="'.wcwh_href( $args ).'"
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i> '.$prnt_title.' </a>';
			break;
			case 'import-stockcount':
				$def_action = $action;
				$action = 'import';
				$title = 'Import Stock Count';

				$btn = '<a class="toolTip linkAction  btn btn-xs btn-info" title="'.$title.'" 
					data-id="'.$id.'" data-action="'.$def_action.'" data-service="'.$services.'" 
					data-modal="wcwhModalImEx" data-actions="close|import" data-title="'.$title.'" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
			break;
			case 'export-variance':
				$action = 'export';
				$title = 'Export Stocktake Variance';

				$args = [ 
					'action' => 'export', 
					'id' => $id, 
					'section' => $this->section_id, 
					'type' => 'variance',
				];

				$btn = '<a class="toolTip btn btn-xs btn-info" title="'.$title.'" 
					href="'.wcwh_href( $args ).'"
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i> '.$prnt_title.' </a>';
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
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_action( $item )
	{
		$actions = [];
		if( $item['status'] >= 1 )
		{
			$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'stocktake', 'title_addon'=>'Stocktake List' ] );
			$actions[] = $this->get_action_btn( $item, 'export-stocktake', [] );
			if( $item['status'] == 3 )
				$actions[] = $this->get_action_btn( $item, 'import-stockcount', [] );
		}

		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_variance( $item )
	{
		$actions = [];
		if( $item['status'] >= 3 )
		{
			$actions[] = $this->get_action_btn( $item, 'print', [ 'print_type'=>'variance', 'title_addon'=>'Stocktake Variance' ] );
			$actions[] = $this->get_action_btn( $item, 'export-variance', [] );
		}

		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
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