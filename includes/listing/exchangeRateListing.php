<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_ExchangeRate_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	public $useFlag;
	private $users;

	protected $section_id = "wh_exchange_rate";

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
			"from_currency"	=> "From Currency",
			"to_currency"	=> "To Currency",
			"base"			=> "Base",
			"rate"			=> "Rate",
			"desc"			=> "Description",
			"status"		=> "Status",
			"approval"		=> "Approval Status",
			"since"			=> "Since Date",
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
			'from_currency' => [ "from_currency", true ],
			'to_currency' => [ "to_currency", true ],
			'rate' => [ "rate", true ],
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
		if( current_user_can( 'approve_'.$this->section_id ) )
			$actions['approve'] = $this->refs['actions']['approve'];
		
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
				'restore' => [ 'restore_'.$this->section_id ],
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
				<label class="" for="flag">By From Currency</label>
				<?php
					$currency = get_woocommerce_currencies();

					$options = options_data( $currency );
                
	                wcwh_form_field( 'filter[from_currency][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['from_currency'] )? $this->filters['from_currency'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By To Currency</label>
				<?php
					$currency = get_woocommerce_currencies();

					$options = options_data( $currency );
                
	                wcwh_form_field( 'filter[to_currency][]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['to_currency'] )? $this->filters['to_currency'] : '', $view 
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

	public function column_from_currency( $item )
	{
		$currency = get_woocommerce_currencies();
		foreach ($currency as $key => $value)
		{
			if($key == $item['from_currency'])
			{
				$html = '<strong>'.$key."</strong><br>(".$value.")";
			}
		}
		return $html;
	}

	public function column_to_currency( $item )
	{
		$currency = get_woocommerce_currencies();
		foreach ($currency as $key => $value)
		{
			if($key == $item['to_currency'])
			{
				$html = '<strong>'.$key."</strong><br>(".$value.")";
			}
		}
		return $html;
	}

	public function column_rate( $item )
	{
		$html = '<strong class="font14"">'.round_to($item['rate'],2,1,1).'</strong>';
		
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