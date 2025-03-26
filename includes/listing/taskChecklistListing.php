<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TaskChecklist_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_task_checklist";

	public $useFlag;

	// public $meta_id;

	protected $users;
	public $warehouse;

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
			'cb'					=> '<input type="checkbox" />',
			"docno" 				=> "Doc No.",
			'_serial2'				=> 'Num/ Task Detail',
			'doc_date'				=> "Action Date",
			'recursive_period'		=> "Recursive Period",
			"status"				=> "Status",
			"remark"				=> "Remark"
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
		];
		
		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'complete_'.$this->section_id ) )
			$actions['complete'] = $this->refs['actions']['complete'];

		if( current_user_can( 'incomplete_'.$this->section_id ) )
			$actions['incomplete'] = $this->refs['actions']['incomplete'];
		
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
				// 'edit' => [ 'wh_admin_support' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'complete' => [ 'complete_'.$this->section_id ],
				// 'edit' => [ 'update_'.$this->section_id ],
				// 'post' => [ 'post_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				// 'edit' => [ 'update_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			'9' => array(
				'view' => [ 'wcwh_user' ],
				'incomplete' => [ 'incomplete_'.$this->section_id ],
			),
		);
	}

	public function _filter_search()
	{
	?>
		<div class="row">
			<div class="col-md-4">
				<label class="" for="flag">By Item</label>
				
			</div>
		</div>
	<?php
	}

	public function get_statuses()
	{
		return array(
			'process' => array( 'key' => 'process', 'title' => 'Processing' ),
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'6'		=> array( 'key' => 'posted', 'title' => 'Posted' ),
			'9'		=> array( 'key' => 'completed', 'title' => 'Completed' ),
			'0'		=> array( 'key' => 'inactive', 'title' => 'Trashed' ),
			// '-1'	=> array( 'key' => 'deleted', 'title' => 'Deleted' ),
		);
	}

	/**
	 *	Custom Listing Column 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function column_cb( $item ) 
	{
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['item_id'], $item['item_id'] );
		
		return $html;
    }

	public function column_docno( $item ) 
	{	
		$item['doc_id'] = $item['item_id'];
		$actions = $this->get_actions( $item, $item['status'], [ 'title'=>$item['docno'] ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column__serial2( $item ) 
	{	
		return sprintf( '%1$s %2$s', '<strong>'.$item['_item_number'].".  &nbsp;".'</strong>', $item['_serial2'] );  
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
		$html = ( $elem )? implode( " > ", $elem ) : '-';
		
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