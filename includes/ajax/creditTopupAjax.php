<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_CreditTopup_AJAX" ) )
{
	
class WCWH_CreditTopup_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "CreditTopup_AJAX";

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
            'wh_credit_topup_action' => false,
            'wh_credit_topup_listing' => false,
            'wh_credit_topup_form' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_CreditTopup_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/creditTopupCtrl.php" ); 
    }


    /**
     *  Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function wh_credit_topup_action()
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

        $filters = array();
        if( !empty( $datas['listing'] ) )
        {
            parse_str( $datas['listing'], $filters );
        }
        
        $this->includes();
        $Inst = new WCWH_CreditTopup_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();

        //Log Activity
        $log_id = 0;
        if( ! in_array( strtolower( $form['action'] ), array( 'edit', 'view' ) ) )
        {
            $log_id = apply_filters( 'wcwh_log_activity', 'save', [
                'section'       => $section_id,
                'action'        => ( $form['action'] )? $form['action'] : ( ( $listForm["action"] != "-1" )? $listForm["action"] : $listForm["action2"] ),
                'ref_id'        => $form['id'],
                'ip_address'    => apply_filters( 'wcwh_get_user_ip', 1 ),
                'agent'         => implode( ', ', $datas['agent'] ),
                'data'          => $datas,
                'status'        => 1,
            ] );
        }

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }
        
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                switch( $action )
                {
                    case 'edit':
                    case 'view':
                        ob_start();
                            $Inst->view_form( $form['id'], false, ( $action == 'view' )? true : false );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'save':
                    case 'update':
                        $succ = false;
                    break;
                    default:
                        if( ! $Inst->validate( $action, $form, $datas ) )
                        {
                            $succ = false;
                            $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
                        }
                        else
                        {
                             $result = $Inst->action_handler( $action, $form, $datas );
                            if( ! $result['succ'] )
                            {
                                $succ = false;
                                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
                            }
                            else
                            {
                                $this->Notices->set_notice( 'success', 'success' );
                            }

                            //reload content
                            if( ! $datas['section'] || ( $datas['section'] && $datas['section'] == $section_id.'-listing-form' ) )
                                $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );
                            $outcome['segments']['.template-container #'.$Inst->tplName['new'].'TPL'] = $this->renew_form( $Inst );
                        }
                    break;
                }
            }
            else if( $succ && $listForm && ( isset( $listForm["action"] ) || isset( $listForm["action2"] ) ) )
            {
                $action = ( $listForm["action"] != "-1" )? $listForm["action"] : $listForm["action2"];
                $listForm['action'] = $action;

                if( ! $Inst->validate( $listForm['action'], $listForm, $datas ) )
                {
                    $succ = false;
                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
                }
                else
                {
                    $result = $Inst->action_handler( $listForm['action'], $listForm, $datas );
                    if( ! $result['succ'] )
                    {
                        $succ = false;
                        $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
                    }
                    else
                    {
                        $this->Notices->set_notice( 'success', 'success' );

                        //reload content
                        if( ! $datas['section'] || ( $datas['section'] && $datas['section'] == $section_id.'-listing-form' ) )
                            $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );
                        $outcome['segments']['.template-container #'.$Inst->tplName['new'].'TPL'] = $this->renew_form( $Inst );
                    }
                }
            }
            else
                $succ = false;
        }
        else
            $succ = false;

        //Update Log
        if( $log_id && ! $succ )
        {
            $log_id = apply_filters( 'wcwh_log_activity', 'update', [
                'id'            => $log_id,
                'status'        => $succ,
                'error_remark'  => $this->Notices->get_notice( 'error' ),
            ] );
        }

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        echo json_encode( $outcome );
        die();
    }
    
    public function wh_credit_topup_listing()
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
        $Inst = new WCWH_CreditTopup_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $form );

        echo json_encode( $outcome );
        die();
    }

    public function wh_credit_topup_form()
    {   
        $this->Notices->reset_operation_notice();
        $succ = true;

        $datas = $this->get_submission();
        if( $datas['agent'] && ! is_array( $datas['agent'] ) ) $datas['agent'] = json_decode( stripslashes( $datas['agent'] ), true );
        
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
        $Inst = new WCWH_CreditTopup_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();
        $key = '_credit_topup';

        //Log Activity
        $log_id = apply_filters( 'wcwh_log_activity', 'save', [
            'section'       => $section_id,
            'action'        => $form['action'],
            'ref_id'        => $form[$key]['id'],
            'ip_address'    => apply_filters( 'wcwh_get_user_ip', 1 ),
            'agent'         => implode( ', ', $datas['agent'] ),
            'data'          => $datas,
            'status'        => 1,
        ] );

        $outcome = array();

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }

        if( $succ && $form )
        {
            if( ! $Inst->validate( $form['action'], $form[$key], $datas ) )
            {
                $succ = false;
                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
            }
            
            if( $succ )
            {
                $result = $Inst->action_handler( $form['action'], $form[$key], $datas );
                if( ! $result['succ'] )
                {
                    $succ = false;
                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
                }
                else
                {
                    $this->Notices->set_notice( 'success', 'success' );
                }
            }
        }
        else
            $succ = false;

        //Update Log
        if( $log_id && ! $succ )
        {
            $log_id = apply_filters( 'wcwh_log_activity', 'update', [
                'id'            => $log_id,
                'status'        => $succ,
                'error_remark'  => $this->Notices->get_notice( 'error' ),
            ] );
        }
        
        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        if( $succ )
        {
            //reload content
            if( ! $datas['section'] || ( $datas['section'] && $datas['section'] == $section_id.'-listing-form' ) )
                $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );
            $outcome['segments']['.template-container #'.$Inst->tplName['new'].'TPL'] = $this->renew_form( $Inst );
            /*
            if( $datas['section'] )
            {
                $section = str_replace( '-listing-form', '', $datas['section'] ); 
                switch( $section )
                {
                    case 'wh_credit':
                        include_once( WCWH_DIR."/includes/controller/customerCtrl.php" ); 
                        $Customer = new WCWH_Customer_Controller();
                        $outcome['segments']['.template-container #'.$Customer->tplName['new'].'TPL'] = $this->renew_form( $Customer );
                    break;
                }
            }
            */
        }

        echo json_encode( $outcome );
        die();
    }
    /**
     *  ---------------------------------------------------------------------------------------------------
     */
}

new WCWH_CreditTopup_AJAX( $refs );
}