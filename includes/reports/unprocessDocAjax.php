<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_UnprocessedDoc_AJAX" ) )
{
    
class WCWH_UnprocessedDoc_AJAX extends WCWH_AJAX
{
    protected $refs;

    public $Notices;
    public $className = "UnprocessedDoc_AJAX";

    /**
     *  Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
        parent::__construct( $refs );

        $this->submission();
    }

    protected function ajax_events( $ajaxs )
    {
        return array_merge( $ajaxs, [
            'unprocessed_doc_report' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_UnprocessedDoc_Rpt' ) ) include_once( WCWH_DIR . "/includes/reports/unprocessDoc.php" ); 
    }

    public function submission()
    {
        $section_id = 'unprocessed_doc_report';
        $datas = $_REQUEST;
        
        if( ! isset( $datas['action'] ) || !in_array( $datas['action'], ['wcwh_'.$section_id.'_submission'] ) ) return false;

        if( $datas['agent'] && ! is_array( $datas['agent'] ) ) $datas['agent'] = json_decode( stripslashes( $datas['agent'] ), true );
        
        $succ = true;
        $error = '';
        $form = array();
        if( is_array( $datas['form'] ) )
            $form = $datas['form'];
        else
            parse_str( $datas['form'], $form );
        
        $filters = array();
        if( !empty( $datas['listing'] ) )
        {
            parse_str( $datas['listing'], $filters );
        }
        
        $this->includes();
        $Inst = new WCWH_UnprocessedDoc_Rpt();

        if( ! isset( $form['section'] ) || $form['section'] != $section_id || ! $form['action'] ) return false;

        $this->Notices->reset_operation_notice();

        //Log Activity
        $log_id = apply_filters( 'wcwh_log_activity', 'save', [
            'section'       => $section_id,
            'action'        => $form['action'],
            'ip_address'    => apply_filters( 'wcwh_get_user_ip', 1 ),
            'agent'         => implode( ', ', $datas['agent'] ),
            'data'          => $datas,
            'status'        => 1,
        ] );
        
        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
            $this->Notices->set_notice( 'invalid-input', 'error' );
        }

        $result = $Inst->action_handler( $form['action'], $form, $datas );
        if( ! $result['succ'] )
        {
            $succ = false;
            $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
        }
        else
        {
            $this->Notices->set_notice( 'success', 'success' );
        }
        $outcome['messages'] = $this->Notices->get_notices();

        //Update Log
        if( $log_id && ! $succ )
        {
            $log_id = apply_filters( 'wcwh_log_activity', 'update', [
                'id'            => $log_id,
                'status'        => $succ,
                'error_remark'  => $this->Notices->get_notice( 'error' ),
            ] );
        }
    }
    
    /**
     *  ---------------------------------------------------------------------------------------------------
     */
    public function unprocessed_doc_report()
    {
        $section_id = 'unprocessed_doc_report';
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
        $Inst = new WCWH_UnprocessedDoc_Rpt();

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }

        if( $datas['diff_seller'] )
        {
            $Inst->seller = $datas['diff_seller'];
            unset( $datas['diff_seller'] );
        }

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $form, [], $section_id );
        $outcome['segments']['#exportUnprocessedDocTPL'] = $this->renew_form( $Inst, 'export_form', '' );

        echo json_encode( $outcome );
        die();
    }
}

new WCWH_UnprocessedDoc_AJAX( $refs );
}