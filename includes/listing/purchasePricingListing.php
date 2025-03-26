<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_PurchasePricing_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_purchase_pricing";

	public $useFlag;

	private $users;

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
			"docno" 		=> "Doc No",
			"code"			=> "Price Code",
			"seller"		=> "Purchaser",
			//"scheme"		=> "Target Type",
			//"ref"			=> "Apply To",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"since"			=> "Effective Date",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			"remarks"		=> "Remark",
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
			'code' => [ "code", true ],
			'since' => [ "since", true ],
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
		
		return $actions;
	}

	public function get_data_alters( $datas = array() )
	{
		if( $datas )
		{
			foreach( $datas as $i => $row )
			{
				$filters = [ 'price_id'=>$row['id'], 'status'=>1 ];
				$refs = apply_filters( 'wcwh_get_price_ref', $filters, [], false, [ 'warehouse'=>1, 'client'=>1, 'scheme'=>1 ] );
				if( $refs )
				{
					$schemes = [];
					$titles = [];
					foreach( $refs as $ref )
					{
						$text = [];
						if( $ref['wh_code'] ) $text[] = $ref['wh_code'];
						if( $ref['wh_name'] ) $text[] = $ref['wh_name'];
						$datas[$i]['seller'][ $ref['wh_id'] ] = implode( ' - ', $text );

						$schemes[] = $ref['scheme'];
						$titles[] = $ref['scheme_title'];

						$text = [];
						if( $ref['client_code'] ) $text[] = $ref['client_code'];
						if( $ref['client_name'] ) $text[] = $ref['client_name'];
						$datas[$i]['ref_client'][ $ref['client_id'] ] = implode( ' - ', $text );
					}

					$datas[$i]['scheme'] = array_unique( $schemes );
					$datas[$i]['scheme'] = $datas[$i]['scheme'][0];

					$datas[$i]['scheme_title'] = array_unique( $titles );
					$datas[$i]['scheme_title'] = $datas[$i]['scheme_title'][0];
				}
			}
		}

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
				//'edit' => [ 'wh_admin_support' ],
				//'restore' => [ 'restore_'.$this->section_id ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
				'approve' => [ 'approve_'.$this->section_id ],
				'reject' => [ 'reject_'.$this->section_id ],
			),
			'2' => array(
				'view' => [ 'wcwh_user' ],
			)
		);
	}

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">By Seller</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'company'=>1 ] ), 'code', [ 'code', 'name' ] );
                
	                wcwh_form_field( 'filter[seller]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['seller'] )? $this->filters['seller'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">by Target Type</label>
				<?php
					$schemes = get_schemes( 'pricing' );
					$options = options_data( $schemes, 'scheme', [ 'title' ] );
                
	                wcwh_form_field( 'filter[scheme]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['scheme'] )? $this->filters['scheme'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Target Client</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                
	                wcwh_form_field( 'filter[ref_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options
	                    ], 
	                    isset( $this->filters['ref_id'] )? $this->filters['ref_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-6">
				<label class="" for="flag">By Item</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
					
	                wcwh_form_field( 'filter[product_id][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['product_id'] )? $this->filters['product_id'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By Approval</label>
				<?php
					$approvals = $this->get_approvals();
					$options = [ ''=>'Select', '-1'=>$approvals['-1']['title'], '0'=>$approvals['0']['title'], '1'=>$approvals['1']['title'] ];
					wcwh_form_field( 'filter[flag]', 
	                    [ 'id'=>'flag', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options, 'offClass'=>true
	                    ], 
	                    isset( $this->filters['flag'] )? $this->filters['flag'] : '', $view 
	                ); 
				?>
			</div>
		</div>
		<div class="row">
			
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

	public function column_docno( $item ) 
	{	
		$status = $item['status'];
		$status = ( $item['status'] > 0 && $item['flag'] > 0 )? 2 : $status;
		$actions = $this->get_actions( $item, $status );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
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

	public function column_seller( $item )
	{
		$html = [];
		if( $item['seller'] )
		{
			foreach( $item['seller'] as $id => $seller )
			{
				$args = [ 'id'=>$id, 'service'=>'wh_warehouse_action', 'title'=>$seller, 'permission'=>[ 'access_wh_warehouse' ] ];
				$html[] = $this->get_external_btn( $seller, $args );
			}
		}
		
		return implode( ',<br>', $html );
	}

	public function column_scheme( $item )
	{		
		return $item['scheme_title'];
	}

	public function column_ref( $item )
	{
		$val = "";
		switch( $item['scheme'] )
		{	
			case 'client_code':
				$html = [];
				if( $item['ref_client'] )
				{
					foreach( $item['ref_client'] as $id => $client )
					{
						$args = [ 'id'=>$id, 'service'=>'wh_client_action', 'title'=>$client, 'permission'=>[ 'access_wh_client' ] ];
						$html[] = $this->get_external_btn( $client, $args );
					}
					$val = implode( ',<br>', $html );
				}
			break;
			default:
				$val = "All";
			break;
		}
		return $val;
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