<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PartsRequest_AJAX" ) )
{
	
class WCWH_PartsRequest_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "PartsRequest_AJAX";

    /**
     *	Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
		parent::__construct( $refs );

        $this->submission();
    }

    protected function ajax_events( $ajaxs )
    {
        return array_merge( $ajaxs, [
            'wh_parts_request_action' => false,
            'wh_parts_request_listing' => false,
            'wh_parts_request_form' => false,
            'wh_parts_request_pre_order' => false,
            'wh_parts_request_pre_order_action' => false,
            'wh_parts_request_pre_order_print' => false,
            'wh_get_parts_pre_orders' => false,
            'wh_get_parts_request' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_PartsRequest_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/partsRequestCtrl.php" ); 
    }

    public function submission()
    {
        $datas = $_REQUEST;

        if( ! isset( $datas['action'] ) || ! in_array( $datas['action'], [ 'wcwh_wh_parts_request_submission', 'print' ] ) ) return false;

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
            if( $datas['view_type'] ) $form['view_type'] = $datas['view_type'];
        }
        
        $this->includes();
        $Inst = new WCWH_PartsRequest_Controller();
        $section_id = $Inst->get_section_id();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

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
    public function wh_parts_request_action()
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
        $Inst = new WCWH_PartsRequest_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();

        //Log Activity
        $log_id = 0;
        if( ! in_array( strtolower( $form['action'] ), array( 'edit', 'view', 'import-stockcount' ) ) )
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
                            $Inst->view_form( $form['id'], false, ( $action == 'view' )? true : false, ( $action == 'update_api' )? true : false );
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
                    case 'parts_request_reference':
                        ob_start();
                            $Inst->gen_form( $form['id'] );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                }
                $outcome['segments']['.template-container #'.$Inst->tplName['multiTR'].'TPL'] = $this->renew_form( $Inst, 'multiTR_form' );
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
                        //$outcome['segments']['.template-container #'.$Inst->tplName['multiTR'].'TPL'] = $this->renew_form( $Inst, 'multiTR_form' );
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

    public function wh_parts_request_listing()
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
        $Inst = new WCWH_PartsRequest_Controller();

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
        //$outcome['segments']['.template-container #'.$Inst->tplName['multiTR'].'TPL'] = $this->renew_form( $Inst, 'multiTR_form' );

        echo json_encode( $outcome );
        die();
    }
    
    public function wh_parts_request_form()
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
        $Inst = new WCWH_PartsRequest_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
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
            //$data['attachment'] = $form["_attachment"];

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
            //$outcome['segments']['.template-container #'.$Inst->tplName['multiTR'].'TPL'] = $this->renew_form( $Inst, 'multiTR_form' );
        }

        echo json_encode( $outcome );
        die();
    }
    /**
     *  ---------------------------------------------------------------------------------------------------
     */

    public function wh_parts_request_pre_order()
    {
        $succ = true;
        $datas = $this->get_submission();
        
        $this->includes();
        $Inst = new WCWH_PartsRequest_Controller();

        $outcome = array();

        if( $succ && $datas && $datas['customer_id'] )
        {
            $dat = [
                'header' => [
                    'customer_id' => $datas['customer_id'],
                    'warehouse_id' => $datas['warehouse_id'],
                    'register_id' => $datas['register_id'],
                    'remark' => $datas['remark'],
                ],
                'detail' => $datas['detail'],
            ];
            
            $result = $Inst->action_handler( 'save', $dat );
            if( ! $result['succ'] )
            {
                $succ = false;
                $message = $Inst->Notices->get_operation_notice();
            }
            else
            {
                if( $succ )
                {
                    $dat = [ 'id'=>$result['id'] ];
                    $result = $Inst->action_handler( 'post', $dat );
                    if( ! $result['succ'] )
                    {
                        $succ = false;
                        $message = $Inst->Notices->get_operation_notice();

                        $result = $Inst->action_handler( 'delete', $dat );
                    }
                }

                if( $succ ) $message = 'Success';
            }

            if( $succ )
            {
                $doc_id = $result['id'][0];
                $print = $Inst->view_receipt( $doc_id );
                if( ! empty( $print ) ) $outcome['print'] = $print;
            }
        }
        else
            $message = "Missng Data";

        $outcome['succ'] = $succ;
        $outcome['message'] = $message;

        echo json_encode( $outcome );
        die();
    }

    public function wh_parts_request_pre_order_action()
    {
        $succ = true;
        $datas = $this->get_submission();
        
        $this->includes();
        $Inst = new WCWH_PartsRequest_Controller();

        $outcome = array();

        if( $succ && $datas )
        {
            $dat = [
                'id' => $datas['doc_id']
            ];

            switch( $datas['action_type'] )
            {
                case 'cancel':
                default:
                    $result = $Inst->action_handler( 'unpost', $dat );
                    if( ! $result['succ'] )
                    {
                        $succ = false;
                        $message = $Inst->Notices->get_operation_notice();
                    }
                    else
                    {
                        if( $succ )
                        {
                            $result = $Inst->action_handler( 'delete', $dat );
                            if( ! $result['succ'] )
                            {
                                $succ = false;
                                $message = $Inst->Notices->get_operation_notice();

                                $result = $Inst->action_handler( 'delete', $dat );
                            }
                        }

                        if( $succ ) $message = 'Pre-Order Cancelled!';
                    }
                break;
            }
        }
        $outcome['succ'] = $succ;
        $outcome['message'] = $message;

        echo json_encode( $outcome );
        die();
    }

    public function wh_parts_request_pre_order_print()
    {
        $succ = true;
        $datas = $this->get_submission();
        
        $this->includes();
        $Inst = new WCWH_PartsRequest_Controller();

        $outcome = array();

        if( $succ && $datas['doc_id'] )
        {
            $doc_id = $datas['doc_id'];
            $print = $Inst->view_receipt( $doc_id );

            if( ! empty( $print ) ) $outcome['print'] = $print;
        }
        $outcome['succ'] = $succ;

        echo json_encode( $outcome );
        die();
    }

    public function wh_get_parts_pre_orders()
    {
        $succ = true;
        $datas = $this->get_submission();
        
        $this->includes();
        $Inst = new WCWH_PartsRequest_Controller();

        $outcome = array();

        if( $succ && $datas )
        {
            $filters = [];
            if( $datas['search'] ) $filters['s'] = $datas['search'];
            if( $datas['register'] ) $filters['register'] = $datas['register'];
            if( $datas['warehouse_id'] ) $filters['warehouse_id'] = $datas['warehouse_id'];
            $outcome = $Inst->get_parts_request_pre_orders( $filters );
        }

        if( $outcome )
        {
            $stringify = [];
            foreach( $outcome as $obj )
            {
                $stringify[] = json_encode( $obj ); 
            }
            echo "[".implode( ",", $stringify )."]";
        }
        else
            echo "[]";
        
        //echo json_encode( $outcome );
        die();
    }

    public function wh_get_parts_request()
    {
        $succ = true;
        $datas = $this->get_submission();
        
        $this->includes();
        $Inst = new WCWH_PartsRequest_Controller();

        $outcome = array();

        if( $succ && $datas )
        {
            $args = [];
            if( $datas['seller'] ) $args['seller'] = $datas['wh_id'];
            if( $datas['docno'] ) $args['docno'] = $datas['docno'];
            $outcome = $Inst->get_parts_request( $args );
        }

        if( $outcome )
        {
            $stringify = [];
            foreach( $outcome as $obj )
            {
                $stringify[] = json_encode( $obj ); 
            }
            echo "[".implode( ",", $stringify )."]";
        }
        else
            echo "[]";
        
        //echo json_encode( $outcome );
        die();
    }
}

new WCWH_PartsRequest_AJAX( $refs );
}