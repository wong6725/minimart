<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_CustomerCredit_AJAX" ) )
{
    
class WCWH_CustomerCredit_AJAX extends WCWH_AJAX
{
    protected $refs;

    public $Notices;
    public $className = "CustomerCredit_AJAX";

    /**
     *  Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
        parent::__construct( $refs );

        $this->submission();
    }

    public function __destruct()
    {
        unset($this->refs);
    }

    protected function ajax_events( $ajaxs )
    {
        return array_merge( $ajaxs, [
            'customer_credit_report' => false,
            'customer_credit_report_detail' => false,
            'customer_credit_detail_report' => false,
            'customer_credit_acc_type_report' => false,
            'customer_credit_limit_report' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_CustomerCredit_Rpt' ) ) include_once( WCWH_DIR . "/includes/reports/customerCredit.php" ); 
    }

    public function submission()
    {
        $section_id = 'customer_credit_report';
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
        $Inst = new WCWH_CustomerCredit_Rpt();

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
    public function customer_credit_report()
    {
        $section_id = 'customer_credit_report';
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
        $Inst = new WCWH_CustomerCredit_Rpt();

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
        $outcome['segments']['#exportCustomerCreditTPL'] = $this->renew_form( $Inst, 'export_form', 'summary' );
        $outcome['segments']['#printCustomerCreditTPL'] = $this->renew_form( $Inst, 'printing_form', 'summary' );
        echo json_encode( $outcome );
        die();
    }

    public function customer_credit_report_detail()
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

        $listForm = array();
        if( !empty( $form['listing_form'] ) )
        {
            parse_str( $form['listing_form'], $listForm );
            unset( $form['listing_form'] );
        }

        if( !empty( $form['info'] ) )
        {
            parse_str( $form['info'], $form['info'] );
            foreach( $form['info'] as $key => $value )
            {
                $form[$key] = $value;
                $listForm[$key] = $value;
            }
            unset( $form['info'] );
        }
        
        $this->includes();
        $Inst = new WCWH_CustomerCredit_Rpt();

        if( ! $this->submission_authenticate( $datas ) )
        {
            $succ = false;
        }

        if( $datas['diff_seller'] )
        {
            $Inst->seller = $datas['diff_seller'];
            unset( $datas['diff_seller'] );
        }
        
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                switch( $action )
                {
                    case 'view':
                        ob_start();
                            $Inst->customer_credit_report_detail( $form['id'], $listForm['filter'] );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                }
            }
            else
                $succ = false;
        }
        else
            $succ = false;

        $outcome['succ'] = $succ;

        echo json_encode( $outcome );
        die();
    }

    public function customer_credit_detail_report()
    {
        $section_id = 'customer_credit_detail_report';
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
        $Inst = new WCWH_CustomerCredit_Rpt();

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
        $outcome['segments']['#exportCustomerCreditTPL'] = $this->renew_form( $Inst, 'export_form', 'details' );
        $outcome['segments']['#printCustomerCreditTPL'] = $this->renew_form( $Inst, 'printing_form', 'details' );

        echo json_encode( $outcome );
        die();
    }

    public function customer_credit_acc_type_report()
    {
        $section_id = 'customer_credit_acc_type_report';
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
        $Inst = new WCWH_CustomerCredit_Rpt();

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
        //$outcome['segments']['#exportCustomerCreditTPL'] = $this->renew_form( $Inst, 'export_form', 'acc_type' );
        //$outcome['segments']['#printCustomerCreditTPL'] = $this->renew_form( $Inst, 'printing_form', 'acc_type' );

        echo json_encode( $outcome );
        die();
    }

    public function customer_credit_limit_report()
    {
        $section_id = 'customer_credit_limit_report';
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
        $Inst = new WCWH_CustomerCredit_Rpt();

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
        $outcome['segments']['#exportCustomerCreditTPL'] = $this->renew_form( $Inst, 'export_form', 'credit_limit' );
        //$outcome['segments']['#printCustomerCreditTPL'] = $this->renew_form( $Inst, 'printing_form', 'credit_limit' );

        echo json_encode( $outcome );
        die();
    }
}

new WCWH_CustomerCredit_AJAX( $refs );
}