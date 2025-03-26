<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_POSOverallChart_AJAX" ) )
{
	
class WCWH_POSOverallChart_AJAX extends WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "POSOverallChart_AJAX";

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
            'pos_overall_summary' => false,
			'pos_overall_category'=>false,
            'pos_overall_item'=>false,

        ] );
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_POSOverallChart' ) ) include_once( WCWH_DIR . "/includes/charts/posOverallChart.php" ); 
    }

    public function submission()
    {
        $section_id = 'pos_overall_chart_export';
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
        $Inst = new WCWH_POSOverallChart();

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
     *  Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function pos_overall_summary()
    {
        $section_id = 'pos_overall_summary';
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
        $Inst = new WCWH_POSOverallChart();

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
        $outcome['segments']['#exportPOSOverallChartTPL'] = $this->renew_form( $Inst, 'export_form', 'summary' );
        $outcome['datas']['chart'] = $Inst->get_chart( $form );

        echo json_encode( $outcome );
        die();
    }
	
	public function pos_overall_category()
    {
        $section_id = 'pos_overall_category';
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
        $Inst = new WCWH_POSOverallChart();

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
        $outcome['segments']['#exportPOSOverallChartTPL'] = $this->renew_form( $Inst, 'export_form', 'category' );
        $outcome['datas']['chart'] = $Inst->get_chart( $form );

        echo json_encode( $outcome );
        die();
    }

    public function pos_overall_item()
    {
        $section_id = 'pos_overall_item';
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
        $Inst = new WCWH_POSOverallChart();

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
        $outcome['segments']['#exportPOSOverallChartTPL'] = $this->renew_form( $Inst, 'export_form', 'item' );
        $outcome['datas']['chart'] = $Inst->get_chart( $form );

        echo json_encode( $outcome );
        die();
    }

    /**
     *  ---------------------------------------------------------------------------------------------------
     */
}

new WCWH_POSOverallChart_AJAX( $refs );
}