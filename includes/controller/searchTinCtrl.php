<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SearchTin_Controller" ) ) 
{

class WCWH_SearchTin_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_search_tin";

	public $Notices;
	public $className = "SearchTin_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newSearchTin',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
		
	}

	public function set_definedSection( $definedSection )
	{
		$this->definedSection = $definedSection;
	}

	public function set_logic()
	{
		
	}

	public function get_section_id()
	{
		return $this->section_id;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/searchTinListing.php" ); 
			$Inst = new WCWH_SearchTin_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );


			$Inst->filters = $filters;
			$this->filter = $filters;
            $Inst->advSearch = array( 'isOn'=>1 );
			$Inst->advSearch_onoff();


			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();
            $args = [];
            if($filters)
            {
            	$wh = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );
				
                $supplier = apply_filters( 'wcwh_get_company', [ 'id'=>$wh['comp_id'] ], [], true, []);
				
                $args['client_tin'] = str_replace(' ', '', $supplier['tin']);
                $args['idType'] = $filters['idType'];
                $args['idValue'] = $filters['idValue'];
                if($filters['taxpayerName'] != null && $filters['taxpayerName'] != '') $args['taxpayerName'] = $filters['taxpayerName'];
				
                $wh_code = $this->refs['einv_id'];
                $api_url = $this->refs['einv_url'];
                $remote = apply_filters( 'wcwh_api_request', 'search_tin', '1', $wh_code, $this->section_id, $args, $api_url );
            
                if( ! $remote['succ'] )
                {
                    $succ = false;
                    $this->Notices->set_notice( $remote['notice'], 'error' );
                }
                else
                {
                    $remote_result = $remote['result'];
                    if( $remote_result['succ'] && $remote_result['data']['status_code'] == '200')
                    {

                        $datas[] = array(
                            'tin' =>  $remote_result['data']['tin'],
                            'idValue' =>  $filters['idValue'],
                            'idType' =>  $filters['idType'],
                            'taxpayerName' =>  $filters['taxpayerName'],
                        );

                    }elseif($remote_result['data']['status_code'] == '404')
                    {

                        $this->Notices->set_notice( 'No Record Found', 'error' );

                    }elseif($remote_result['data']['status_code'] == '400')
                    {

                        $this->Notices->set_notice( 'More than 1 record matches, try searching without taxpayer name or another ID', 'warning' );

                    }
                    else
                    {
                        $succ = false;
                        $this->Notices->set_notice( $remote_result['notification'], 'error' );
                    }
                }

            }
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}