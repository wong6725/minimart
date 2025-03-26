<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_SaleCDNote_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_sale_cdnote";

	public $useFlag;

	public $forfeitFlagCheck = true;

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
			"cd"			=> "Credit/Debit Note",
			"ref_doc"		=> "Ref Doc",
			"client"		=> "Client",
			"doc_date"		=> "Doc Date",
			"post_date"		=> "Post Date",
			"note_reason"	=> "Reason",
			"status"		=> "Status",
			"irb_status"    => "IRB Status",
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
			'post_date' => [ "post_date", true ],
			"ref_doc"		=> "Ref Doc",
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

	public function get_statuses()
	{
		return array(
			'process' => array( 'key' => 'process', 'title' => 'Processing' ),
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'1'		=> array( 'key' => 'active', 'title' => 'Ready' ),
			'3'		=> array( 'key' => 'confirm', 'title' => 'Released' ),
			'6'		=> array( 'key' => 'posted', 'title' => 'Posted' ),
			'9'		=> array( 'key' => 'completed', 'title' => 'Completed' ),
			'10'	=> array( 'key' => 'closed', 'title' => 'Closed' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'Trashed' ),
			'-1'	=> array( 'key' => 'deleted', 'title' => 'Deleted' ),
		);
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
			'3' => array(
				'view' => [ 'wcwh_user' ],
				'refute' => [ 'unpost_'.$this->section_id ],
				//'post' => [ 'post_'.$this->section_id ],
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

			<div class="col-md-4">
				<label class="" for="flag">By Credit/Debit Note</label>
				<?php
					$note_actions = ['2'=>'Debit Note', '1'=>'Credit Note'];
                    wcwh_form_field( 'filter[note_action][]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                            'options'=> $note_actions,'multiple'=>1
                        ], 
	                    isset( $this->filters['note_action'] )? $this->filters['note_action'] : '', $view 
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
			case 'confirm':
				//if( $this->useFlag && $item['flag'] == 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="Release to MyInvoice" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to Release '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ 'post' ].'"></i></a>';
			break;
			case 'refute':
				//if( $this->useFlag && $item['flag'] == 0 )
				$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.'" title="Revert from MyInvoice" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to Revert '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ 'unpost' ].'"></i></a>';
			break;
			case 'sale_credit_note':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printCN" 
				 	data-title="Print Credit Note" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> CN</a>';
			break;
			case 'sale_debit_note':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printDN" 
				 	data-title="Print Debit Note" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> DN</a>';
			break;
			case 'irb_view':
				$link = $this->refs['irb_url'];

				if( $item['uuid'] && $item['longid'] )
				{
					$btn = '<a class="btn btn-xs btn-info" data-title="View on IRB" 
						href="'.$link.$item['uuid'].'/share/'.$item['longid'].'"
						target="_blank"
						'.$html_attr.' 
						><i class="fa fa-search"></i> IRB</a>';
				}
				else
					$btn = '';
			break;
			case 'myinvoice':
				if( $item['uuid'] && $item['longid'] )
				{
					$btn = '<a class="btn btn-xs btn-info" data-title="View MyInvoice" 
						href="'.$this->refs['einv_url']."?action=myinv_einvoice_print&docno=".$item['docno']."&uuid=".$item['uuid'].'&longid='.$item['longid'].'"
						target="_blank"
						'.$html_attr.' 
						><i class="fa fa-search"></i> MyInv</a>';
				}
				else
					$btn = '';
			break;
			case 'cancel':
				$now = current_time( 'mysql' );
				
				if( (int)$item['validate_date'] && (int)time_diff( $item['validate_date'], $now ) <= (int)(71.5*60*60) )
				{
					$btn = '<a class="linkAction  btn btn-xs btn-light btn-none-'.$action.' btn-none-delete" title="'.$actions[ $action ].'" 
					data-id="'.$id.'" data-action="'.$action.'" data-service="'.$services.'"
					data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$actions[ $action ].'" data-tpl="remark" 
					data-message="Confirm to '.$actions[ $action ].' '.$title.'?" 
					'.$html_attr.' 
					><i class="fa '.$icons[ $action ].'"></i></a>';
				}
				else $btn = "";
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
		if( $this->useFlag && $item['flag'] <= 0 )
		{
			$stage = apply_filters( 'wcwh_get_doc_stage', [ 'ref_type'=>$this->section_id, 'ref_id'=>$item['id'] ], [], true );
			if( $stage && $stage['proceed_status'] == 20 ) $item['status'] = 3;
		}

		$this->print_view = true;
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );

		if( $item['status'] == 6 && in_array( strtoupper( $item['irb_status'] ), [ 'V' ] ) )
		{
			$actions['cancel'] = $this->get_action_btn( $item, 'cancel' ); 
		}
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_cd( $item )
	{
		$actions = [];

		if($item['doc_type'] == 'sale_debit_note')
		{
			$actions['sale_debit_note'] = $this->get_action_btn( $item, 'sale_debit_note' ); 
		}
		else if($item['doc_type'] == 'sale_credit_note')
		{
			$actions['sale_credit_note'] = $this->get_action_btn( $item, 'sale_credit_note' ); 
		}

		if( in_array( strtoupper( $item['irb_status'] ), [ 'V', 'C' ] ) )
		{
			$actions['irb_view'] = $this->get_action_btn( $item, 'irb_view' ); 
		}
		if( in_array( strtoupper( $item['irb_status'] ), [ 'V' ] ) )
		{
			$actions['myinvoice'] = $this->get_action_btn( $item, 'myinvoice' ); 
		}
		
		return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
	}

	public function column_ref_doc( $item )
	{
		$html = $item['ref_doc'];
		if( $item['ref_doc_id'] )
		{
			switch( $item['ref_doc_type'] )
			{
				case 'sale_order':
					$args = [ 'id'=>$item['ref_doc_id'], 'service'=>'wh_sales_order_action', 'title'=>$html, 'permission'=>[ 'access_wh_sales_order' ] ];
				break;
			}

			$html = $this->get_external_btn( $html, $args );

		}
		return $html;
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

	public function column_irb_status( $item )
	{
		$statusMapping = [
			"V" => ['color' => 'posted', 'word' => 'Valid'],
			"I" => ['color' => 'inactive', 'word' => 'Invalid'],
			"C" => ['color' => 'inactive', 'word' => 'Cancel'],
			"S" => ['color' => 'active', 'word' => 'Submit'],
		];
	
		if (!empty($item['irb_status']) && isset($statusMapping[$item['irb_status']]))
		{
			$color = $statusMapping[$item['irb_status']]['color'];
			$word = $statusMapping[$item['irb_status']]['word'];
			$html = "<span class='list-stat list-{$color}'>{$word}</span>";
		}
		else
		{
			$html = '';
		}

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