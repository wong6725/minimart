<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_TODO_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_todo";

	public $action_type = "approval";

	public $bulk_actions = array();

	protected $tables = array();

	private $users;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		$this->set_db_tables();

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

	public function set_db_tables()
	{
		global $wcwh;
		$prefix = $wcwh->prefix;

		$this->tables = array(
			"todo" 			=> $prefix."todo",
			"todo_action"	=> $prefix."todo_action",
			"arrangement"	=> $prefix."todo_arrangement",
			"stage_header"	=> $prefix."stage_header",
			"stage_details"	=> $prefix."stage_details",
			"section"		=> $prefix."section",
		);
	}
	
	public function get_columns() 
	{
		return array(
			'cb'			=> '<input type="checkbox" />',
			"title" 		=> "Title",
			"docno"			=> "Todo Doc No.",
			"section_name" 	=> "Document Section",
			"created_by"	=> "Created By",
			"created_at"	=> "Created Date",
			"desc"			=> "Description",
		);
	}

	public function get_hidden_column()
	{
		return array();
	}

	public function get_sortable_columns()
	{
		$col = [
			'docno' => [ "docno", true ],
			'section_name' => [ "section_name", true ],
			'created_at' => [ "created_at", true ],
		];

		return $col;
	}

	public function get_bulk_actions() 
	{
		$actions = $this->bulk_actions;
		
		return $actions;
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
				$needAlters = false;
				if( ! $data['created_by'] || ! $data['doc_title'] ) $needAlters = true;

				if( $needAlters )
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
					
					$temp[$i]['details'] = $result;
					$temp[$i]['created_by'] = $result['created_by'];
					$temp[$i]['replacer'] = [ 'find'=>$find, 'replace'=>$replace ];
				}

				//actions
				if( ! $actions[ $data['arr_id'] ] )
				{
					$tbl = $this->tables['todo_action'];
					$sql = "SELECT * FROM {$tbl} WHERE arr_id = '{$data['arr_id']}' ;";
					$results = $Inst->rawSelects( $sql );
					if( $results )
					{
						$action = array();
						foreach( $results as $j => $row )
						{
							$action[ $row['id'] ] = $row;
						}
						$actions[ $data['arr_id'] ] = $action;
					}
				}
				if( $actions[ $data['arr_id'] ] )
				{
					$temp[$i]['actions'] = $actions[ $data['arr_id'] ];
				}
			}
			
			$bulk_actions = array();
			foreach( $actions as $i => $arr_actions )
			{
				foreach( $arr_actions as $act )
				{
					$right = is_array( $act['responsible'] )? $act['responsible'] : [ $act['responsible'] ];
					if( current_user_cans( $right ) )
					{
						$bulk_actions[ $act['next_action'] ] = $this->refs['actions'][ $act['next_action'] ];
					}
				}
			}
			
			$datas = $temp;
			$this->bulk_actions = $bulk_actions;
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

	public function column_title( $item ) 
	{	
		$out = !empty( $item['doc_title'] )? $item['doc_title'] : $item['title'];
		$out = !empty( $out )? str_replace( $item['replacer']['find'], $item['replacer']['replace'], $out ) : '';
		$icons = $this->get_icons();
		$actions = $this->get_actions( $item, 'default', [ 'id'=>$item['ref_id'], 'services'=>$item['section']."_action", 'title'=>$out ] );

		if( $item['actions'] ){
			foreach( $item['actions'] as $id => $act )
			{
				$right = is_array( $act['responsible'] )? $act['responsible'] : [ $act['responsible'] ];
				if( current_user_cans( $right ) )
				{
					$a = $this->refs['actions'][ $act['next_action'] ];

					$actions[ $id ] = '<a class="linkAction btn btn-xs btn-light btn-none-'.$act['next_action'].'" title="'.$a.'" 
						data-id="'.$item['id'].'" data-action="'.$act['next_action'].'" data-service="'.$this->section_id.'_action" 
						data-modal="wcwhModalConfirm" data-actions="no|yes" data-title="'.$a.' '.$out.'" data-tpl="remark" 
						data-message="Confirm to '.$a.' '.$item['name'].'?"
						><i class="fa '.$icons[ $act['next_action'] ].'"></i></a>';
				}
			}
		}

		//print_data($item);
		return sprintf( '%1$s %2$s', '<strong>'.$out.'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_desc( $item ) 
	{	
		$out = !empty( $item['desc'] )? str_replace( $item['replacer']['find'], $item['replacer']['replace'], $item['desc'] ) : '';
		
		return $out;  
	}
	
	public function column_created_by( $item )
	{
		$user = ( $this->users )? $this->users[ $item['created_by'] ] : $item['created_by'];
		$user = is_array( $user )? ( $user['name']? $user['name'] : $user['display_name'] ) : $user;

		return $user;
	}
	
} //class