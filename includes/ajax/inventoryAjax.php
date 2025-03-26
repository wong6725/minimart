<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Inventory_AJAX" ) )
{
	
class WCWH_Inventory_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "Inventory_AJAX";

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
            'wh_inventory_action' => false,
            'wh_inventory_listing' => false,
        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_Inventory_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/inventoryCtrl.php" ); 
    }

    public function submission()
    {
        $datas = $_REQUEST;

        if( ! isset( $datas['action'] ) || ! in_array( $datas['action'], [ 'wcwh_wh_inventory_submission', 'print' ] ) ) return false;

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
        $Inst = new WCWH_Inventory_Controller();
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
    public function wh_inventory_action()
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
        $Inst = new WCWH_Inventory_Controller();

        $section_id = $Inst->get_section_id();

        if( ! $this->submission_authenticate( $datas, $section_id ) )
        {
            $succ = false;
        }
        
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                $filters = $listForm['filter'];

                if( $datas['wh'] )
                {
                    $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
                    if( $warehouse )
                        $Inst->set_warehouse( $warehouse );
                }

                switch( $action )
                {
                    case 'transact_in':
                        ob_start();
                            $filters['transact'] = '+';
                            $Inst->view_transaction( $form['id'], $filters );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'transact_out':
                        ob_start();
                            $filters['transact'] = '-';
                            $Inst->view_transaction( $form['id'], $filters );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'transact':
                        ob_start();
                            $Inst->view_transaction( $form['id'], $filters );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'reserved':
                        ob_start();
                            $Inst->view_reserved( $form['id'], $filters );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'movement':
                        ob_start();
                            $Inst->view_movement( $form['id'], $filters );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'pos_sales':
                        ob_start();
                            $Inst->view_pos_sales( $form['id'], $filters );
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
                    $Init = []; $type = '';
                    switch( $listForm['action'] )
                    {
                        case 'wh_purchase_request':
                            include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
                            $Init = new WCWH_PurchaseRequest_Controller();
                            $outcome['modal']['title'] = "New Purchase Request";
                        break;
                        case 'wh_purchase_order':
                            include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
                            $Init = new WCWH_PurchaseOrder_Controller();
                            $outcome['modal']['title'] = "New Purchase Order";
                        break;
                        case 'wh_sales_order':
                            include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
                            $Init = new WCWH_SaleOrder_Controller();
                            $outcome['modal']['title'] = "New Sales Order";
                        break;
                        case 'wh_good_issue_own_use':
                            include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
                            $Init = new WCWH_GoodIssue_Controller();
                            $outcome['modal']['title'] = "New Company Use";
                            $type = 'own_use';
                        break;
                        case 'wh_good_issue_reprocess':
                            include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
                            $Init = new WCWH_GoodIssue_Controller();
                            $outcome['modal']['title'] = "New Reprocess";
                            $type = 'reprocess';
                        break;
                        case 'wh_good_issue_block_stock':
                            include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
                            $Init = new WCWH_GoodIssue_Controller();
                            $outcome['modal']['title'] = "New Block Stock";
                            $type = 'block_stock';
                        break;
                        case 'wh_stock_adjust':
                            include_once( WCWH_DIR . "/includes/controller/adjustmentCtrl.php" ); 
                            $Init = new WCWH_Adjustment_Controller();
                            $outcome['modal']['title'] = "New Adjustment";
                        break;
                    }
                    
                    if( $datas['wh'] )
                    {
                        $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
                        if( $warehouse )
                            $Init->set_warehouse(  $warehouse );
                    }

                    ob_start();

                        if( in_array( $listForm['action'], ['wh_good_issue_own_use', 'wh_good_issue_reprocess', 'wh_good_issue_block_stock'] ) )
                            $Init->gen_form( $listForm['id'], $type );
                        else
                            $Init->gen_form( $listForm['id'] );
                    $outcome['content']['.modal-body'] = ob_get_clean();
                    
                }
            }
            else
                $succ = false;
        }
        else
            $succ = false;

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        echo json_encode( $outcome );
        die();
    }

    public function wh_inventory_listing()
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
        $Inst = new WCWH_Inventory_Controller();

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
        $outcome['segments']['#exportInvTPL'] = $this->renew_form( $Inst, 'export_form' );

        echo json_encode( $outcome );
        die();
    }
    /**
     *  ---------------------------------------------------------------------------------------------------
     */
}

new WCWH_Inventory_AJAX( $refs );
}