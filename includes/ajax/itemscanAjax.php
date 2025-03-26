<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ItemScan_AJAX" ) )
{
class WCWH_ItemScan_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "ItemScan_AJAX";

    /**
     *	Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
		parent::__construct( $refs );
    }

    protected function ajax_events( $ajaxs )//ajax
    {
        return array_merge( $ajaxs, [
            'wh_items_scan_listing' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_Item_Scan_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/itemscan_Ctrl.php" );
    }

    public function wh_items_scan_listing()
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
        $Inst = new WCWH_Item_Scan_Controller();

        $section_id = $Inst->get_section_id();

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }

        $filters['_sku'] = trim($form['filter']['qs']);

        ob_start();
        $Inst->display_item($filters, false, true);
        $outcome['content']['.closable_division'] = ob_get_clean();

        if(!$outcome['content']['.closable_division'])
        {
            unset($outcome['content']['.closable_division']);
        }

        $outcome['value'] = $filters['_sku'];
        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        echo json_encode( $outcome );
        die();
    }
}

new WCWH_ItemScan_AJAX( $refs );
}
