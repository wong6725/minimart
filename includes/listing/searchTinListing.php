<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

class WCWH_SearchTin_Listing extends WCWH_Listing 
{	
	protected $refs;

	public $filters = array();
	public $viewStats = array();

	protected $section_id = "wh_production_in";

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
			"no" => "",
			"taxpayerName" => "Taxpayer",
            "idType" => "Id Type",
			"idValue" 	=> "ID",
			"tin" 	=> "Tin",
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
	
	}

	public function filter_search()
	{
        ?>
		<div class="row">
            <div class="col col-md-6 segment">
				<label class="" for="flag">By Taxpayer Name</label>
				<?php 
	                wcwh_form_field( 'filter[taxpayerName]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>[''] ], 
	                    isset( $this->filters['taxpayerName'] )? $this->filters['taxpayerName'] : '', $view
	                ); 
				?>
			</div>

            <div class="col col-md-3 segment direction Sent">
				<label class="" for="flag">By ID Type </label>
				<?php
					$options = [''=>'', 'BRN'=>"BRN", 'PASSPORT'=>'PASSPORT', 'NRIC'=>'NRIC', 'ARMY'=>'ARMY' ];
                
	                wcwh_form_field( 'filter[idType]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
	                        'options'=> $options,
	                    ], 
	                    isset( $this->filters['idType'] )? $this->filters['idType'] : '', $view 
	                ); 
				?>
			</div>
            <div class="col col-md-3 segment direction Sent ">
				<label class="" for="flag">ID</label>
				<?php
	               wcwh_form_field( 'filter[idValue]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[],
	                        'options'=> $options, 'multiple'=>1
	                    ], 
	                    isset( $this->filters['idValue'] )? $this->filters['idValue'] : '', $view 
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
	public function column_no( $item ) 
	{
		$this->no +=1;
		
		return $this->no;
    }

	public function column_taxpayerName( $item ) 
	{
		return $item['taxpayerName'];
    }

	public function column_idType( $item ) 
	{
		return $item['idType'];
    }

	public function column_idValue( $item ) 
	{
		return $item['idValue'];
    }

	public function column_tin( $item ) 
	{
		return $item['tin'];
    }
	
} //class