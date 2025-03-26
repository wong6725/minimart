<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TODOHistory_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_todo";

	public $action_type = "approval";

	public $bulk_actions = array();

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
			"doc_title" 	=> "Document",
			"section_name"	=> "Section",
			"docno"			=> "Todo Doc",
			"action_type"	=> "Action Type",
			"status"		=> "Status",
			"created"		=> "Created",
			"action_taken"	=> "Action Taken",
			"remark"		=> "Remark",
			"action_by"		=> "Action By",
			"action_at"		=> "Action Date",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'doc_title' => [ "doc_title", true ],
			'docno' => [ "docno", true ],
			'section_name' => [ "section_name", true ],
			'created' => [ "created_at", true ],
			'action_at' => [ "action_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = $this->bulk_actions;
		
		return $actions;
	}

	public function get_statuses()
	{
		return array(
			'all'	=> array( 'key' => 'all', 'title' => 'All' ),
			'0'		=> array( 'key' => 'active', 'title' => 'Ready' ),
			'1'		=> array( 'key' => 'completed', 'title' => 'Completed' ),
		);
	}

	public function get_approvals()
	{
		return array(
			''		=> array( 'key' => 'pending', 'title' => 'Pending' ),
			'approve'		=> array( 'key' => 'approved', 'title' => 'Approved' ),
			'reject'	=> array( 'key' => 'rejected', 'title' => 'Rejected' ),
		);
	}

	public function get_data_alters( $datas = array() )
	{
		if( $datas )
		{
			$Inst = new WCWH_CRUD_Controller();
			$prefix = $Inst->get_prefix();
			$actions = array();

			$temp = $datas;
			foreach( $temp as $i => $data )
			{
				if( ! $data['doc_title'] )
				{
					//description
					$find = [ 'section'=>'{section}', 'action_type'=>'{action_type}' ];
					$replace = [ 'section'=>$data['section_name'], 'action_type'=>$this->refs['action_type'][ $this->action_type ] ];

					if( ! $data['table'] ) continue;

					$tbl = $prefix.$data['table'];
					$primary_key = ( $data['table_key'] )?  $data['table_key'] : 'id';
					$sql = "SELECT * FROM {$tbl} WHERE {$primary_key} = '{$data['ref_id']}' ;";
					$result = $Inst->rawSelect( $sql );
					if( $result )
					{
						$find['custno'] = '{custno}'; 	$replace['custno'] = ( $result['custno'] )? $result['custno'] : '';
						$find['code'] = '{code}'; 		$replace['code'] = ( $result['code'] )? $result['code'] : '';
						$find['regno'] = '{regno}'; 	$replace['regno'] = ( $result['regno'] )? $result['regno'] : '';
						$find['name'] = '{name}'; 		$replace['name'] = ( $result['name'] )? $result['name'] : '';
						$find['docno'] = '{docno}'; 	$replace['docno'] = ( $result['docno'] )? $result['docno'] : '';
						$find['sdocno'] = '{sdocno}'; 	$replace['sdocno'] = ( $result['sdocno'] )? $result['sdocno'] : '';
						$find['serial'] = '{serial}'; 	$replace['serial'] = ( $result['serial'] )? $result['serial'] : '';
					}
					
					$temp[$i]['replacer'] = [ 'find'=>$find, 'replace'=>$replace ];
				}
			}
			$datas = $temp;
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

	public function filter_search()
	{
	?>
		<div class="row">
			<div class="col-md-4">
				<label class="" for="flag">By Type</label>
				<?php
					$options = options_data( apply_filters( 'wcwh_get_arrangement', [], [], false, [] ), 'id', [ 'section_title' ], 'Select' );
	                wcwh_form_field( 'filter[arr_id]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options 
	                    ], 
	                    isset( $this->filters['arr_id'] )? $this->filters['arr_id'] : '', $view 
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
		$html = sprintf( '<input type="checkbox" name="id[]" value="%s" title="%s" />', $item['id'], $item['id'] );
		
		return $html;
    }

	public function column_doc_title( $item ) 
	{	
		$out = !empty( $item['doc_title'] )? $item['doc_title'] : $item['title'];
		$out = !empty( $out )? str_replace( $item['replacer']['find'], $item['replacer']['replace'], $out ) : '';
		$actions = $this->get_actions( $item, 'default', [ 'id'=>$item['ref_id'], 'services'=>$item['section']."_action", 'title'=>$out ] );
		
		return sprintf( '%1$s %2$s', '<strong>'.$out.'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_status( $item )
	{
		$statuses = $this->get_statuses();
		$html = "<span class='list-stat list-{$statuses[ $item['flag'] ]['key']}'>{$statuses[ $item['flag'] ]['title']}</span>";

		return $html;
	}

	public function column_action_type( $item )
	{
		return $this->refs['action_type'][$item['action_type']];
	}

	public function column_action_taken( $item )
	{
		$approval = $this->get_approvals();
		$html = "<span class='list-stat list-{$approval[ $item['next_action'] ]['key']}'>{$approval[ $item['next_action'] ]['title']}</span>";

		return $html;
	}

	public function column_action_by( $item )
	{
		$user = ( $this->users )? $this->users[ $item['action_by'] ] : $item['action_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;

		return $user;
	}

	public function column_created( $item )
	{
		$user = ( $this->users )? $this->users[ $item['created_by'] ] : $item['created_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;
		$date = $item['created_at'];

		$html = $user.'<br>'.$date;
		return $html;
	}
	
} //class