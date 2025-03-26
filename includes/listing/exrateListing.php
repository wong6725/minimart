<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_ExRate_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_exchange_rate";

	//public $useFlag;

	private $users;

	public function __construct()
	{
		$this->set_refs();
		parent::__construct();

		//$this->users = get_simple_users();
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
			//'cb'			=> '<input type="checkbox" />',
			"since" 		=> "Effective Date",
			"docno" 		=> "Doc No",
			"from_currency"	=> "From Currency",
			"to_currency"	=> "To Currency",
			"rate"			=> "Rate",
		);
	}

	/*
	public function get_hidden_column()
	{
		$col = [];
		if( ! $this->useFlag ) $col[] = 'approval';

		return $col;
	}
	*/

	public function get_sortable_columns()
	{
		$col = [
			'docno' => [ "docno", true ],
			'from_currency' => [ "from_currency", true ],
			'to_currency' => [ "to_currency", true ],
			'rate' => [ "rate", true ],
		];

		return $col;
	}

	/*
	public function get_bulk_actions() 
	{
		$actions = array();

		if( current_user_can( 'save_'.$this->section_id ) )
			$actions['wh_exchange_rate'] = "New Exchange Rate";
		
		return $actions;
	}
	*/

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
			),
			'1' => array(
				'view' => [ 'wcwh_user' ],
			)
		);
	}

	public function filter_search()
	{
		?>
		<div class="row">
			<div class="segment col-md-4">
				<label class="" for="flag">Exchange Rate On Date</label><br>
				<?php
					wcwh_form_field( 'filter[on_date]', 
	                    [ 'id'=>'on_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
							'attrs'=>[ 'data-dd-format="Y-m-d"'], 'offClass'=>true
	                    ], 
	                    isset( $this->filters['on_date'] )? $this->filters['on_date'] : '', $view 
	                ); 
				?>
			</div>

			<div class="segment col-md-4">
				<label class="" for="flag">By From Currency</label>
				<?php
					$currency = get_woocommerce_currencies();
					$options = options_data( $currency );
                
	                wcwh_form_field( 'filter[from_currency][]', 
	                    [ 'id'=>'from_currency', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
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
	                    [ 'id'=>'to_currency', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['to_currency'] )? $this->filters['to_currency'] : '', $view 
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
		//$status = ( $item['status'] > 0 && $item['flag'] > 0 )? 2 : $status;
		$actions = $this->get_actions( $item, $status );
		
		return sprintf( '%1$s %2$s', '<strong>'.$item['docno'].'</strong>', $this->row_actions( $actions, true ) );  
	}

	public function column_from_currency( $item )
	{
		$currency = get_woocommerce_currencies();
		foreach ($currency as $key => $value)
		{
			if($key == $item['from_currency'])
			{
				$html = '<strong class="font14"">'.$key."</strong><br>(".$value.")";
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
				$html = '<strong class="font14">'.$key."</strong><br>(".$value.")";
			}
		}
		return $html;
	}

	public function column_rate( $item )
	{
		$html = '<strong class="font18">'.round_to( $item['rate'], 2, 1, 1 ).'</strong>';
		
		return $html;
	}

	/*
	public function column_since( $item )
	{
		$html = '<strong class="font14"">'.$item['since'].'</strong>';
		
		return $html;
	}*/
	
} //class