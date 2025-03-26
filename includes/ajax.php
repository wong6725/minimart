<?php

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

if ( !class_exists( "WCWH_AJAX" ) )
{
	
class WCWH_AJAX
{
	protected $refs;

	public $Notices;
	public $className = "AJAX";

    /**
     *	Hook into ajax events
     */
    public function __construct( $refs = array() )
    {
		global $wcwh;
		$this->refs = ( $refs )? $refs : $wcwh->get_plugin_ref();

		$this->Notices = new WCWH_Notices();
        
        $ajax_events = $this->ajax_events( array(
        	'general' => true,

            'view_attachment' => false,
            
            //wh_stage
            'wh_stage_action' => false,
            'wh_stage_listing' => false,
        	//wh_logs
            'wh_logs_action' => false,
            'wh_logs_listing' => false,
            'wh_mail_logs_action' => false,
            'wh_mail_logs_listing' => false,
			//wh_sync
            'wh_sync_action' => false,
			'wh_sync_listing' => false,
            'wh_sync_form' => false,

        //wh_config
            //wh_stockout
            'wh_stockout_action' => false,
            'wh_stockout_listing' => false,
            'wh_stockout_form' => false,
            //wh_scheme
            'wh_scheme_action' => false,
            'wh_scheme_listing' => false,
            'wh_scheme_form' => false,
            //wh_section
            
            //wh_status
            'wh_status_action' => false,
            'wh_status_listing' => false,
            'wh_status_form' => false,

            //dynamic country state
            'dynamicCountryState' => false,
		) );

        foreach( $ajax_events as $ajax_event => $nopriv )
        {
            //if( method_exists( $this, $ajax_event ) )
            //{
                add_action( 'wp_ajax_wcwh_' . $ajax_event, array( $this, $ajax_event ) );
                if( $nopriv ) add_action( 'wp_ajax_nopriv_wcwh_' . $ajax_event, array( $this, $ajax_event ) );

                add_action( 'wcwh_ajax_wcwh_' . $ajax_event, array( $this, $ajax_event ) );
                if( $nopriv ) add_action( 'wcwh_ajax_nopriv_wcwh_' . $ajax_event, array( $this, $ajax_event ) );
            //}
            //else
            //{
            //    add_action( 'wp_ajax_wcwh_' . $ajax_event, array( $this, 'ajax_error' ) );
            //   if( $nopriv ) add_action( 'wp_ajax_nopriv_wcwh_' . $ajax_event, array( $this, 'ajax_error' ) );
            //}
        }

        add_action( 'admin_init', array( $this, 'setting_handler' ), 10 );
    }

    public function __destruct()
    {
        unset($this->refs);
        unset($this->Notices);
    }

    protected function ajax_events( $ajaxs )
    {
        return $ajaxs;
    }

    private function json_headers()
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    public function get_submission()
    {
    	return $_REQUEST;
    }

    public function submission_authenticate( $datas = array(), $key = "" )
    {
        $form = array();
        if( is_array( $datas['form'] ) )
            $form = $datas['form'];
        else
            parse_str( $datas['form'], $form );

        if( in_array( strtolower( $form['action'] ), array( 'view', 'view_doc' ) ) )
            return true;

    	if( ! apply_filters( 'wcwh_verify_token', $datas['token'], $key ) )
    	{ 
    		$this->Notices->set_notice( 'invalid-submit', 'error' );
			return false;
		}

		return true;
    }

    public function renew_listing( $Inst, $filters = array(), $orders = array(), $function = '' )
    {
    	if( ! $Inst ) return;

    	$rules = array();
        if( !empty( $filters['filter'] ) ) $rules = array_merge( $rules, $filters['filter'] );
    	if( !empty( $filters['adv_filter'] ) ) $rules = array_merge( $rules, $filters['adv_filter'] );

    	ob_start();
        
        if( !empty( $function ) )
            $Inst->$function( $rules, $orders );
        else   
    		$Inst->view_listing( $rules, $orders );

    	return ob_get_clean();
    }

    public function renew_form( $Inst, $function = '', $args = '' )
    {
        if( ! $Inst ) return;
        
        ob_start();
     
        if( !empty( $function ) )
        {
            if( $args ) $Inst->$function( $args );
            else $Inst->$function();
        }
        else   
        {
            if( $args ) $Inst->view_form( $args );
            else $Inst->view_form();
        }

        return ob_get_clean();
    }

    public function segmentify( $Inst, $function = '' )
    {
        if( ! $Inst || ! $function ) return;

        ob_start();
            
            $Inst->$function();

        return ob_get_clean();
    }

    //----- Form Restoration 09/11/22
    public function renew_fragment_button( $Inst, $action='', $div = false, $class = 'col-md-2', $id = 0)
    {
        if( ! $Inst || ! $action ) return;

        if( $div && !$id ) return;

        ob_start();

        if($div):
        ?>
        <div class="<?php echo $class ?>" id="<?php echo $id ?>">
            
        <?php
        endif;
        $Inst->view_fragment($action);

        if($div):
        ?>
        </div>
        <?php
        endif;

        return ob_get_clean();
    }

    public function create_form_transient( $section_id, $datas, $expiry = 1800)
    {
        if( !$section_id || !$datas || !$datas['action'] ) return false;

        $action = strtolower( $datas['action'] );
        unset($datas['action']);
        $datas['timestamp'] = current_time('timestamp');        

        $succ = set_transient( get_current_user_id().$section_id.'_'.$action.'_form', $datas, $expiry );

        return $succ;
    }

    public function delete_form_transient( $section_id, $action)
    {
        if( !$section_id || !$action ) return false;

        $succ = delete_transient( get_current_user_id().$section_id.'_'.$action.'_form', $datas );

        return $succ;
    }
    //----- Form Restoration 09/11/22


    /**
     *	general Ajax
     *	---------------------------------------------------------------------------------------------------
     */
    public function general()
    {
    	$outcome = [ 'succ' => false, 'connection' => 1 ];

    	echo json_encode( $outcome );
        die();
    }

    public function ajax_error()
    {
        $this->Notices->reset_operation_notice();
        $outcome = [ 'succ' => false, 'connection' => 1 ];
        $outcome['submission'] = $this->get_submission();

        $this->Notices->set_notice( 'submission-fail', 'error' );
        $outcome['messages'] = $this->Notices->get_notices();

        echo json_encode( $outcome );
        die();
    }

    public function setting_handler()
    {
        $this->Notices->reset_operation_notice();
        $succ = true;

        $datas = $this->get_submission();

        if( ! $this->submission_authenticate( $datas, 'wh_setting' ) || ! $datas['wcwh_option'] )
        {
            $succ = false;
        }

        if( $succ )
        {   
            update_option( 'wcwh_option', $datas['wcwh_option'] );
        }
        
        return $succ;
    }


    public function view_attachment()
    {
        $this->Notices->reset_operation_notice();
        $succ = true;
        $outcome = array();

        $datas = $this->get_submission();

        if ( !class_exists( "WCWH_Files" ) ) include_once( WCWH_DIR . "/includes/files.php" );
        $Inst = new WCWH_Files();

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
        {
            $succ = false;
        }

        if( $succ )
        {
            $Inst->view_attachment( $datas['id'], $datas );
        }

        die();
    }


    /**
     *  wh_stage Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function wh_stage_action()
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
            }
            unset( $form['info'] );
        }

        $filters = array();
        if( !empty( $datas['listing'] ) )
        {
            parse_str( $datas['listing'], $filters );
        }
        
        if( ! class_exists( 'WCWH_Stage_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/stageCtrl.php" );
        $Inst = new WCWH_Stage_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
        {
            $succ = false;
        }
        
        $action = '';
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                switch( $action )
                {
                    case 'view':
                        ob_start();
                            $Inst->view_form( $form['id'], false, ( $action == 'view' )? true : false );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'view_doc':
                        $doc_section = str_replace( '-listing-form', '', $datas['section'] );
                        ob_start();
                            $Inst->view_doc_stage( $form['id'], $doc_section );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'save':
                    case 'update':
                    default:
                        $succ = false;
                    break;
                }
            }
            else
                $succ = false;
        }
        else
            $succ = false;

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        if( ! in_array( $action, [ 'view', 'view_doc' ] ) )
            $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );

        echo json_encode( $outcome );
        die();
    }

    public function wh_stage_listing()
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

        if( ! class_exists( 'WCWH_Stage_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/stageCtrl.php" );
        $Inst = new WCWH_Stage_Controller();

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
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
    /**
     *  ---------------------------------------------------------------------------------------------------
     */


    /**
     *  wh_logs Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function wh_logs_action()
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
            }
            unset( $form['info'] );
        }

        $filters = array();
        if( !empty( $datas['listing'] ) )
        {
            parse_str( $datas['listing'], $filters );
        }
        
        if( ! class_exists( 'WCWH_ActivityLog_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/activityLogCtrl.php" ); 
        $Inst = new WCWH_ActivityLog_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
        {
            $succ = false;
        }
        
        $action = '';
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                switch( $action )
                {
                    case 'view':
                        ob_start();
                            $Inst->view_form( $form['id'], false, ( $action == 'view' )? true : false );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'view_doc':
                        $doc_section = str_replace( '-listing-form', '', $datas['section'] );
                        ob_start();
                            $Inst->view_doc_log( $form['id'], $doc_section );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'save':
                    case 'update':
                    default:
                        $succ = false;
                    break;
                }
            }
            else
                $succ = false;
        }
        else
            $succ = false;

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        if( ! in_array( $action, [ 'view', 'view_doc' ] ) )
            $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );

        echo json_encode( $outcome );
        die();
    }

    public function wh_logs_listing()
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
        
        if( ! class_exists( 'WCWH_ActivityLog_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/activityLogCtrl.php" ); 
        $Inst = new WCWH_ActivityLog_Controller();

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
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

    public function wh_mail_logs_action()
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
            }
            unset( $form['info'] );
        }

        $filters = array();
        if( !empty( $datas['listing'] ) )
        {
            parse_str( $datas['listing'], $filters );
        }
        
        if( ! class_exists( 'WCWH_MailLog_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/mailLogCtrl.php" ); 
        $Inst = new WCWH_MailLog_Controller();

        if( $datas['wh'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['wh'] ], [], true, [ 'company'=>1 ] );
            if( $warehouse )
                $Inst->set_warehouse( $warehouse );
        }

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
        {
            $succ = false;
        }
        
        $action = '';
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                switch( $action )
                {
                    case 'view':
                        ob_start();
                            $Inst->view_form( $form['id'], false, ( $action == 'view' )? true : false );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'save':
                    case 'update':
                    default:
                        $succ = false;
                    break;
                }
            }
            else
                $succ = false;
        }
        else
            $succ = false;

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        if( ! in_array( $action, [ 'view' ] ) )
            $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );

        echo json_encode( $outcome );
        die();
    }

    public function wh_mail_logs_listing()
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
        
        if( ! class_exists( 'WCWH_MailLog_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/mailLogCtrl.php" ); 
        $Inst = new WCWH_MailLog_Controller();

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
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
    /**
    *  ---------------------------------------------------------------------------------------------------
    */
	
    
    /**
     *  wh_sync Ajax
     *  ---------------------------------------------------------------------------------------------------
     */
    public function wh_sync_action()
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
            }
            unset( $form['info'] );
        }

        $filters = array();
        if( !empty( $datas['listing'] ) )
        {
            parse_str( $datas['listing'], $filters );
        }
        
        if( ! class_exists( 'WCWH_SYNC_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );
        $Inst = new WCWH_SYNC_Controller();

        $section_id = $Inst->get_section_id();
        $tplName = $Inst->tplName;

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
        {
            $succ = false;
        }
        
        $action = '';
        if( $succ && $form )
        {
            if( $form['action'] )
            {
                $action = strtolower( $form['action'] );
                switch( $action )
                {
                    case 'view':
                    case 'edit':
                        ob_start();
                            $Inst->view_form( $form['id'], false, ( $action == 'view' )? true : false );
                        $outcome['content']['.modal-body'] = ob_get_clean();
                    break;
                    case 'sync':
                        $Inst->sync_remote_api( $form['id'] );
                    break;
                    case 'sync_reference':
                        $Inst->sync_remote_api();
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

        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        if( !in_array( $action, [ 'view' ] ) )
            $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $form );

        echo json_encode( $outcome );
        die();
    }

	public function wh_sync_listing()
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
        
        if( ! class_exists( 'WCWH_SYNC_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" ); 
        $Inst = new WCWH_SYNC_Controller();

        $section_id = $Inst->get_section_id();

        if( ! $this->submission_authenticate( $datas, $Inst->get_section_id() ) )
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

    public function wh_sync_form()
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
        
        if( ! class_exists( 'WCWH_SYNC_Controller' ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" ); 
        $Inst = new WCWH_SYNC_Controller();

        $section_id = $Inst->get_section_id();
        $key = '_sync';

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
        
        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();

        //Listing
        if( $succ )
        {
            //reload content
            if( ! $datas['section'] || ( $datas['section'] && $datas['section'] == $section_id.'-listing-form' ) )
                $outcome['segments']['.'.$section_id.'-listing-form'] = $this->renew_listing( $Inst, $filters );
            $outcome['segments']['.template-container #'.$Inst->tplName['new'].'TPL'] = $this->renew_form( $Inst );
        }

        echo json_encode( $outcome );
        die();
    }
    /**
     *  ---------------------------------------------------------------------------------------------------
     */

    public function dynamicCountryState()
    {
        $this->Notices->reset_operation_notice();
        $succ = true;

        $datas = $this->get_submission();
        $outcome = array();

        $def_country = !empty( $datas['country'] )? $datas['country'] : 'MY';

        $states = WCWH_Function::get_states( $def_country );
        $state_options = options_data( $states );
        
        $outcome['succ'] = $succ;
        $outcome['messages'] = $this->Notices->get_notices();
        $outcome['state'] = $state_options;

        echo json_encode( $outcome );
        die();
    }
}

}