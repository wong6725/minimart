<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TaskSchedule_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_task_schedule";

	public $useFlag;

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
			"warehouse_id"			=> "Company / Outlet",
			"doc_date"				=> "Action Date",
			"last_run_date"			=> "Last Run Date",
			// "next_date"				=> "Next Run Date",
			"status"				=> "Status",
			"approval"				=> "Approval Status",
			"created"				=> "Created",
			"lupdate"				=> "Updated",
			"remark"				=> "Remark",
			// 'serial2'				=> 'serial2',
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
			'last_run_date' => [ "last_run_date", true ],
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
		if( current_user_can( 'complete_'.$this->section_id ) )
			$actions['complete'] = $this->refs['actions']['complete'];

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
		$status = array(
			'0' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'wh_admin_support' ],
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
				'edit' => [ 'update_'.$this->section_id ],
				'delete' => [ 'delete_'.$this->section_id ],
				'post' => [ 'post_'.$this->section_id ],
			),
			'6' => array(
				'view' => [ 'wcwh_user' ],
				// 'edit' => [ 'wh_admin_support' ],
				'unpost' => [ 'unpost_'.$this->section_id ],
				'complete' => [ 'complete_'.$this->section_id ],
			),
			// '9' => array(
			// 	'view' => [ 'wcwh_user' ],
			// 	'edit' => [ 'wh_admin_support' ],
			// 	'duplicate' => [ 'duplicate_'.$this->section_id ],
			// 	'incomplete' => [ 'incomplete_'.$this->section_id ],
			// 	//'close' => [ 'close_'.$this->section_id ],
			// ),
		);

		if ( !class_exists( "WCWH_TaskSchedule_Class" ) ) include_once( WCWH_DIR . "/includes/controller/task-schedule.php" );
		$Inst = new WCWH_TaskSchedule_Class();
		
		$datas = $Inst->get_checklist(['doc_id' => $item['doc_id'],'bqty' => 1, 'dstatus' => 6 ], false);
		$check = true;
		foreach($datas as $data){
			if($data['hstatus'] == 9) $check = false;
		}
		if(!$check){
			unset($status['6']['unpost']);
		}

		return $status;
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