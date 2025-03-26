<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_PosCashWithdrawal_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_pos_cash_withdrawal";

	private $users;
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
			"print"			=> "Print",
			"withdraw_person" => "Collector",
			"withdrawal_amount" => "Amount",
			// "bankin_person" => "Bank In Person",
			// "bankin_date" => "Bank In Date",
			"doc_date"		=> "Doc Date",
            "post_date"		=> "Post Date",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"created"		=> "Created",
			"lupdate"		=> "Updated",
			
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
			
			'created' => [ "created_at", true ],
			'lupdate' => [ "lupdate_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'access_wh_pos_cash_withdrawal' ) )
			$actions['delete'] = $this->refs['actions']['delete'];
		if( current_user_can( 'access_wh_pos_cash_withdrawal'  ) )
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
				//'edit' => [ 'wh_admin_support' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'access_wh_pos_cash_withdrawal'],
				'delete' => [ 'access_wh_pos_cash_withdrawal'],
				'post' => [ 'access_wh_pos_cash_withdrawal'],
				//'approve' => [ 'approve_'.$this->section_id ],
				//'reject' => [ 'reject_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
				'unpost' => [ 'access_wh_pos_cash_withdrawal'],
				'complete' => [ 'access_wh_pos_cash_withdrawal'],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				// 'edit' => [ 'wh_admin_support' ],
				//'duplicate' => [ 'duplicate_'.$this->section_id ],
				'incomplete' => [ 'access_wh_pos_cash_withdrawal' ],
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
			case 'cash_withdrawal':
				$btn = '<a class="btn btn-xs btn-info toggle-modal" data-action="print" data-tpl="printCW" 
				 	data-title="Print Cash Withdrawal" data-modal="wcwhModalImEx" data-actions="close|printing" 
					data-id="'.$id.'"
					><i class="fa fa-print" aria-hidden="true"></i> CW</a>';
			break;
			
		}

		return $btn;
	}


	public function filter_search()
	{
		if($this->filters['to_date'])
		{
			$to_date = date( 'Y-m-d', strtotime( $this->filters['to_date'] ) );
		}
		if($this->filters['from_date'])
		{
			$from_date = date( 'Y-m-d', strtotime( $this->filters['from_date']) );
		}
			
				
		$def_from = date( 'm/d/Y', strtotime( $this->filters['from_date']?$this->filters['from_date']:current_time( 'Y-m-1' ) ) );
		$def_to = date( 'm/d/Y', strtotime( $this->filters['to_date']?$this->filters['to_date']:current_time( 'Y-m-t' ) ) );
	?>
		<div class="row">
			<div class="col-md-4 segment">
				<label class="" for="flag">From Date <sup>Current: <?php echo $this->filters['from_date']; ?></sup></label><br>
				<?php
					wcwh_form_field( 'filter[from_date]', 
	                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'],
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $from_date )? $from_date : '', $view 
	                ); 
				?>
			</div>

			<div class="col-md-4 segment">
				<label class="" for="flag">To Date <sup>Current: <?php echo $this->filters['to_date']; ?></sup></label><br>
				<?php
					wcwh_form_field( 'filter[to_date]', 
	                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ], 'offClass'=>true
	                    ], 
	                    isset( $to_date )? $to_date : '', $view 
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
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['doc_id'], $item['doc_id'] );
		
		return $html;
    }

	public function column_docno( $item ) 
	{	
		$actions = $this->get_actions( $item, $item['status'], ['title'=>$item['docno']] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_withdraw_person( $item )
	{
		return $item['withdraw_person'];
	}

	public function column_withdrawal_amount( $item )
	{
		// $html = number_format($item['amt'],2);
		
		return $item['amt'];
	}

	
		
	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['status'] ]['key']}'>{$statuses[ $item['status'] ]['title']}</span>";

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

	public function column_print( $item )
	{
			$actions = [];
			if( $item['status'] > 0 )
			{
				$actions['cash_withdrawal'] = $this->get_action_btn( $item, 'cash_withdrawal',['force'=>1] ); 
			}

			return sprintf( '%1$s', $this->row_actions( $actions, true ) );  
		
	}
	
	
} //class