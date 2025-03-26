<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_SearchTin_AJAX" ) )
{
	
class WCWH_SearchTin_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "SearchTin_AJAX";

    /**
     *	Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
		parent::__construct( $refs );
    }

    protected function ajax_events( $ajaxs )
    {
        return array_merge( $ajaxs, [
            'wh_search_tin_listing' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_SearchTin_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/searchTinCtrl.php" ); 
    }

   
    /**
     *  Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function wh_search_tin_listing()
    {
        $this->Notices->reset_operation_notice();
        $succ = true;
        $outcome = array();

        $datas = $this->get_submission();
        $form = array();
        if( is_array( $datas['form'] ) )
            $form = $datas['form'];
        else
            parse_str( $datas['form'], $form );

        $this->includes();
        $Inst = new WCWH_SearchTin_Controller();

        $section_id = $Inst->get_section_id();

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }

        //Listing
        $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $form );

        $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        echo json_encode( $outcome );
        die();
    }

    
}

new WCWH_SearchTin_AJAX( $refs );
}