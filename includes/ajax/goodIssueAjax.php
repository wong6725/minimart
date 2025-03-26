<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_GoodIssue_AJAX" ) )
{
	
class WCWH_GoodIssue_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "GoodIssue_AJAX";

    /**
     *	Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
		parent::__construct( $refs );

        $this->submission();
        //add_action( "admin_init", array( $this, "submission" ), 1000 );
    }

    public function __destruct()
    {
        unset($this->refs);
    }

    protected function ajax_events( $ajaxs )
    {
        return array_merge( $ajaxs, [
            'wh_good_issue_action' => false,
            'wh_good_issue_listing' => false,
            'wh_good_issue_form' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_GoodIssue_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
    }

    public function submission()
    {
        $datas = $_REQUEST;

        if( ! isset( $datas['action'] ) || ! in_array( $datas['action'], [ 'wcwh_wh_delivery_order_submission', 'wcwh_wh_good_issue_submission', 'print' ] ) ) return false;

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

        if( ! $form && ! $filters )
        {
            if( $datas['id'] ) $form['id'] = $datas['id'];
            if( $datas['action'] ) $form['action'] = $datas['action'];
            if( $datas['section'] ) $form['section'] = $datas['section'];
            if( $datas['type'] ) $form['type'] = $datas['type'];
        }
        
        $this->includes();
        $Inst = new WCWH_GoodIssue_Controller();
        $section_id = $Inst->get_section_id();

        if( ! isset( $form['section'] ) || $form['section'] != $section_id || ! $form['action'] ) return false;

        $this->Notices->reset_operation_notice();

        //Log Activity
        $log_id = apply_filters( 'wcwh_log_activity', 'save', [
            'section'       => $section_id,
            'action'        => $form['action'],
            'ref_id'        => $form['id'],
            'ip_address'    => apply_filters( 'wcwh_get_user_ip', 1 ),
            'agent'         => ( $datas['agent'] )? implode( ', ', $datas['agent'] ) : '',
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

        //Update Log
        if( $log_id && ! $succ )
        {
            $log_id = apply_filters( 'wcwh_log_activity', 'update', [
                'id'            => $log_id,
                'status'        => $succ,
                'error_remark'  => $this->Notices->get_notice( 'error' ),
            ] );
        }

        $outcome['messages'] = $this->Notices->get_notices();
    }


    /**
     *  Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function wh_good_issue_action()
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
        $Inst = new WCWH_GoodIssue_Controller();

        if( $datas['ref_doc_type'] )
        {
            $Inst->ref_doc_type = $datas['ref_doc_type'];
            unset( $datas['ref_doc_type'] );
        }
        if( $datas['ref_issue_type'] )
        {
            $Inst->ref_issue_type = $datas['ref_issue_type'];
            unset( $datas['ref_issue_type'] );
        }
        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse(  $warehouse );
        }

        $section_id = $Inst->get_section_id();

        //Log Activity
        $log_id = 0;
        if( ! in_array( strtolower( $form['action'] ), array( 'edit', 'view', 'good_issue_reference' ) ) )
        {
            $log_id = apply_filters( 'wcwh_log_activity', 'save', [
                'wh_code'       => $datas['wh'],
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

                            if( method_exists( $Inst, 'view_reference' ) )
                                $outcome['segments']['#good_issue_reference_content'] = $this->segmentify( $Inst, 'view_reference' );
                        }
                    break;
                    case 'good_issue_delivery_order':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'delivery_order' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_own_use':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'own_use' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_vending_machine':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'vending_machine' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_reprocess':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'reprocess' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_block_stock':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'block_stock' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_transfer_item':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'transfer_item' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_direct_consume':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'direct_consume' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'good_issue_other':
                        ob_start();
                            $Inst->gen_form( $form['id'], 'other' );
                        $outcome['content']['.modal-body'] = ob_get_clean();
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

                        if( method_exists( $Inst, 'view_reference' ) )
                            $outcome['segments']['#good_issue_reference_content'] = $this->segmentify( $Inst, 'view_reference' );
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

    public function wh_good_issue_listing()
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
        $Inst = new WCWH_GoodIssue_Controller();

        if( $datas['ref_doc_type'] )
        {
            $Inst->ref_doc_type = $datas['ref_doc_type'];
            unset( $datas['ref_doc_type'] );
        }
        if( $datas['ref_issue_type'] )
        {
            $Inst->ref_issue_type = $datas['ref_issue_type'];
            unset( $datas['ref_issue_type'] );
        }
        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse(  $warehouse );
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

        if( method_exists( $Inst, 'view_reference' ) )
                $outcome['segments']['#good_issue_reference_content'] = $this->segmentify( $Inst, 'view_reference' );

        echo json_encode( $outcome );
        die();
    }
    
    public function wh_good_issue_form()
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
        $Inst = new WCWH_GoodIssue_Controller();

        if( $datas['ref_doc_type'] )
        {
            $Inst->ref_doc_type = $datas['ref_doc_type'];
            unset( $datas['ref_doc_type'] );
        }
        if( $datas['ref_issue_type'] )
        {
            $Inst->ref_issue_type = $datas['ref_issue_type'];
            unset( $datas['ref_issue_type'] );
        }
        if( $datas['wh'] )
        {   
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            
            if( $warehouse )
                $Inst->set_warehouse(  $warehouse );
        }

        $section_id = $Inst->get_section_id();

        //Log Activity
        $log_id = apply_filters( 'wcwh_log_activity', 'save', [
            'wh_code'       => $datas['wh'],
            'section'       => $section_id,
            'action'        => $form['action'],
            'ref_id'        => $form['_form']['doc_id'],
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
            $data = array();
            $data['header'] = $form["_form"];
            $data['detail'] = $form["_detail"];
            $data['attachment'] = $form["_attachment"];

            if( ! $Inst->validate( $form['action'],  $data, $datas ) )
            {
                $succ = false;
                $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
            }

            if( $succ )
            {   
                $result = $Inst->action_handler( $form['action'], $data, $datas );
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

            if( method_exists( $Inst, 'view_reference' ) )
                $outcome['segments']['#good_issue_reference_content'] = $this->segmentify( $Inst, 'view_reference' );
        }

        echo json_encode( $outcome );
        die();
    }
    /**
     *  ---------------------------------------------------------------------------------------------------
     */
}

new WCWH_GoodIssue_AJAX( $refs );
}