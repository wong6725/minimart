<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_CreditTopup_Class" ) ) include_once( WCWH_DIR . "/includes/classes/credit-topup.php" ); 

if ( !class_exists( "WCWH_CreditTopup_Controller" ) ) 
{
	
class WCWH_CreditTopup_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_credit_topup";

	protected $primary_key = "id";

	public $Notices;
	public $className = "CreditTopup_Controller";

	public $Logic;
	protected $PC;

	public $tplName = array(
		'new' 		=> 'newCreditTopup',
	);

	public $useFlag = false;
	public $automate = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();
		
		$this->set_logic();
	}

	public function arrangement_init()
	{
		$Inst = new WCWH_TODO_Class();

		$arr = $Inst->get_arrangement( [ 'section'=>$this->section_id, 'action_type'=>'approval', 'status'=>1 ] );
		if( $arr )
		{
			$this->useFlag = true;
		}
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_CreditTopup_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		
		$this->PC =  new WC_POS_CreditLimit_Class();
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;

		if( ! isset( $this->warehouse['permissions'] ) )
		{
			$metas = get_warehouse_meta( $this->warehouse['id'] );
			$this->warehouse = $this->combine_meta_data( $this->warehouse, $metas );
		}

		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] )
			$this->view_outlet = true;

		$this->Logic->setWarehouse( $this->warehouse );
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'customer_id' => 0,
			'customer_code' => '',
			'sapuid' => '',
			'credit_limit' => 0,
			'percentage' => 0,
			'effective_from' => '',
			'effective_to' => '',
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_uniqueFields()
	{
		return array();
	}

	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
			'filter',
			'_wpnonce',
			'action2',
			'_wp_http_referer',
		);
	}

	public function validate( $action , $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $action || $action < 0 )
		{
			$succ = false;
			$this->Notices->set_notice( 'invalid-action', 'warning' );
		}

		if( ! $datas )
		{
			$succ = false;
			$this->Notices->set_notice( 'insufficient-data', 'warning' );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch( $action )
			{
				case 'update':
				case 'restore':
				case 'delete':
				case 'approve':
				case 'reject':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
			}
		}

		return $succ;
	}

	public function validate_unique( $action, $datas = array() )
	{
		$succ = true;

		$unique = $this->get_uniqueFields();
		if( $unique )
		{
			foreach( $unique as $key )
			{
				$result = $this->Logic->get_infos( [ $key => $datas[$key] ], [], true );
				if( $result )
				{	
					if( ! $datas[ $this->get_primaryKey() ] || 
						( $datas[ $this->get_primaryKey() ] && $datas[ $this->get_primaryKey() ] != $result[ $this->get_primaryKey() ] ) )
					{
						$succ = false;
					}
				}
			}
		}

		if( ! $succ )
			$this->Notices->set_notice( 'not-unique', 'error' );

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$datas['lupdate_by'] = $user_id;
			$datas['lupdate_at'] = $now;

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$datas = $this->data_sanitizing( $datas );
					
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$customer = array();
					if( !empty( $datas['customer_id'] ) )
					{
						$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$datas['customer_id'] ], [], true, [ 'usage'=>1 ] );
					}
					else if( !empty( $datas['customer_code'] ) )
					{
						$customer = apply_filters( 'wcwh_get_customer', [ 'code'=>$datas['customer_code'] ], [], true, [ 'usage'=>1 ] );
					}
					else if( !empty( $datas['sapuid'] ) )
					{
						$customer = apply_filters( 'wcwh_get_customer', [ 'uid'=>$datas['sapuid'] ], [], true, [ 'usage'=>1 ] );
					}
					if( $customer )
					{
						$datas['customer_id'] = $customer['id'];
						$datas['customer_code'] = ( $datas['customer_code'] )? $datas['customer_code'] : $customer['code'];
						$datas['sapuid'] = ( $datas['sapuid'] )? $datas['sapuid'] : $customer['uid'];
						
						if( ! $this->automate )
						{
							$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $customer['id'], $customer );
							$from_date = strtotime( $user_credits['from_date']." 00:00:00" );
							$to_date = strtotime( $user_credits['to_date']." 23:59:59" );
						}
					}
					else
					{
						$succ = false;
						$this->Notices->set_notice( "invalid-record", "error" );
					}
					
					$datas['percentage'] = ( $datas['percentage'] > 0 )? $datas['percentage'] : 100;
					
					$eff_date = strtotime( $datas['effective_from'] );
					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}
						
						if( ! $this->automate && ( $eff_date >= $from_date && $eff_date <= $to_date ) && ( $user_credits['usable_credit'] + $datas['credit_limit'] < 0 ) )
						{
							$succ = false;
							$this->Notices->set_notice( 'Please be mind that Current Usable: '.$user_credits['usable_credit'].', Afterward: '.( $user_credits['usable_credit']+$datas['credit_limit'] ), 'warning' );
						}

						$datas['created_by'] = $user_id;
						$datas['created_at'] = $now;

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}
					
					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}
						
						$prev = $this->Logic->get_infos( [ 'id'=>$datas[ $this->get_primaryKey() ] ], [], true, [] );
						if( $prev ) $credit_limit = $datas['credit_limit'] - $prev['credit_limit'];
						if( ( $eff_date >= $from_date && $eff_date <= $to_date ) && ( $user_credits['usable_credit'] + $credit_limit < 0 ) )
						{
							$succ = false;
							$this->Notices->set_notice( 'Please be mind that Current Usable: '.$user_credits['usable_credit'].', Afterward: '.( $user_credits['usable_credit']+$credit_limit ), 'warning' );
						}
					}

					//$datas = $this->json_encoding( $datas );

					if( $succ )
					{
						$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];

							if( $isSave )
							{
								//Doc Stage
						        $stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
						            'ref_type'		=> $this->section_id,
						            'ref_id'		=> $result['id'],
						            'action'        => $action,
						            'status'    	=> 1,
						        ] );
						    }
						}
					}
				break;
				case "delete":
				case "delete-permanent":
				case "restore":
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$datas['id'] = $id;
							$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}
							
							if( $succ )
							{
								$outcome['id'][] = $result['id'];

								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
								] );
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "approve":
				case "reject":
					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];
					
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $metas['remark'] )? $metas['remark'] : '' );
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );
							
								$datas['flag'] = 0;
								$datas['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $datas['flag'] );

								$datas['id'] = $id;
								$result = $this->Logic->action_handler( $action, $datas, $metas, $obj );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];

									//Doc Stage
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
									    'ref_type'	=> $this->section_id,
									    'ref_id'	=> $result['id'],
									    'action'	=> $action,
									    'status'    => $status,
									    'remark'	=> ( $metas['remark'] )? $metas['remark'] : '',
									] );
								}
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "print":
					$this->print_form( $datas['id'] );

					exit;
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
            if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }
        finally
        {
        	if( $succ )
                if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
            else 
                if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}

	public function after_action( $succ, $id, $action = "save" )
	{
		if( ! $id ) return $succ;

		if( $succ )
		{
			$id = is_array( $id )? $id : [ $id ];

			foreach( $id as $ref_id )
			{
				$succ = apply_filters( 'wcwh_todo_arrangement', $ref_id, $this->section_id, $action );
				if( ! $succ )
				{
					$this->Notices->set_notice( 'arrange-fail', 'error' );
				}
			}
		}

		return $succ;
	}

	public function auto_topup_handler()
	{
		$succ = true;

		$opts = $this->setting['middleware'];
		if( ! $opts['use_integrate'] ) return true;
		if( ! $opts['url'] || ! $opts['key'] ) return true;

		$acc_types = apply_filters( 'wcwh_get_account_type', [], [], false, [ 'auto_topup'=>1 ] );
		if( $acc_types )
		{
			foreach( $acc_types as $i => $acc_type )
			{
				if( $acc_type['auto_topup'] && strlen( $acc_type['plant'] ) > 0 && $acc_type['term_id'] > 0 )
				{
					$this->sap_middleware_integration( $acc_type );
				}
			}
		}

		return $succ;
	}
		public function sap_middleware_integration( $acc_type = [] )
		{	
			if( ! $acc_type ) return false;

			$now = current_time('mysql');
			$opts = $this->setting['middleware'];
			$opts['period'] = ( $opts['period'] > 0 && $opts['period'] < 8 )? $opts['period'] : 7;
			$acc_type['topup_time'] = ( $acc_type['topup_time'] )? $acc_type['topup_time'] : '00:00:01';

			$logs_key = 'wcwh_auto_topup_runner';
			$runner_logs = get_option( $logs_key, false );
			if( !empty( $runner_logs[ $acc_type['plant'] ] ) )
			{
				if( strtotime( $runner_logs[ $acc_type['plant'] ]['next_runtime'] ) > strtotime( $now ) )
					return true;
			}
			else
			{
				if( current_time( 'N' ) != $opts['period'] ) return true;
				if( strtotime( current_time( 'y-m-d' ).' '.$acc_type['topup_time'] ) > strtotime( $now ) ) return true;
			}

			$remote_url = $opts['url'];
			$key = $opts['key'];
			
			$find = [ 'year'=>'{year}', 'month'=>'{month}', 'day'=>'{day}' ];
			$replace = [ 'year'=>current_time("y"), 'month'=>current_time("m"), 'day'=>strtoupper( current_time("d") ) ];
			$key = str_replace( $find, $replace, $key );

			$api_key = password_hash( $key, PASSWORD_BCRYPT, [ 'cost'=>12 ] );

			$remote_url.= isset( $acc_type['sv'] )? $acc_type['sv']."/" : "0/";
			if( defined( 'developer_debug' ) ) echo $remote_url;

			$headers = [ 
				'Content-Type' => 'application/json',
				'X-SAPRFC-API-Key-Token' => $api_key,
			];
			
			$records_found = 0;
			$term = $this->PC->get_acc_type_credit_term( $acc_type['id'] );
			if( $term )
			{
				$day_of_month = ( $term )? $term['days'] : 1;
				$offset = ( $term )? $term['offset'] : 0;

				$date_range = $this->PC->get_credit_period( $day_of_month, $offset, $term['term_id'] );
				$acc_type['date_from'] = $date_from = $date_range['from'];
				$acc_type['date_to'] = $date_to = $date_range['to'];
				
				$datas = [ 
					'plant' => $acc_type['plant'], 
					'beginDate' => date( 'Ymd', strtotime( $date_from ) ),
					'endDate' => date( 'Ymd', strtotime( current_time( 'mysql' )." -1 days" ) ),
					'ee' => 0,
				];
				$datas = json_encode( $datas ); 
				if( defined( 'developer_debug' ) ) pd($datas);
				
				add_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10, 4 );
				$response = wp_remote_post( $remote_url, [ 'timeout'=>45, 'headers'=>$headers, 'body'=>$datas, 'sslverify' => false ] );
				remove_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10 );
				
				if( ! is_wp_error( $response ) ) 
				{
					$response = json_decode( wp_remote_retrieve_body( $response ), true );
					if( defined( 'developer_debug' ) ){ pd( $response ); }
					
					if( $response ) 
					{	
						foreach( $response as $id => $tki )
						{
							if( $tki && $tki['pernr'] ) $records_found+= 1;

							$succ = $this->auto_topup( $acc_type, $tki );
						}
					}
				}
				else
				{
					if( defined( 'developer_debug' ) ){ pd( $response->get_error_message() ); }
				}
				
				if( defined( 'developer_debug' ) ) echo "<br><hr><br>";
			}

			if( $records_found > 0 )
			{
				$runner_logs[ $acc_type['plant'] ] = [
					'prev_runtime' => $now,
					'next_runtime' => date( 'Y-m-d ', strtotime( current_time( "Y-m-d" )." +7 days" ) ).$acc_type['topup_time'],
				];
			}
			update_option( $logs_key, $runner_logs );
			
			return true;
		}
		
		public function auto_topup( $acc_type, $tki = [] )
		{
			if( ! $tki || ! $tki['pernr'] ) return true;
			
			$opts = $this->setting['middleware'];
			
			$customer = apply_filters( 'wcwh_get_customer', [ 'uid'=>str_pad( $tki['pernr'], 6, '0', STR_PAD_LEFT ), 'acc_type'=>$acc_type['id'] ], [], true, [ 'group'=>1, 'uid_strip'=>6 ] );
			if( ! $customer ) return true;
			
			$term = $this->PC->get_user_credit_term( $customer['id'], $customer['cgroup_id'] );
			$credits = $term['credit_limit'];
			
			$date_from = $acc_type['date_from'];
			$date_to = $acc_type['date_to'];
			if( ! $acc_type['date_from'] || ! $acc_type['date_to'] )
			{
				$at_term = $this->PC->get_acc_type_credit_term( $customer['acc_type'] );
				$term['term_id'] = !empty( $at_term['term_id'] )? $at_term['term_id'] : $term['term_id'];
				$term['days'] = !empty( $at_term['days'] )? $at_term['days'] : $term['days'];
				$term['offset'] = !empty( $at_term['offset'] )? $at_term['offset']: $term['offset'];
				
				$day_of_month = ( $term )? $term['days'] : 1;
				$offset = ( $term )? $term['offset'] : 0;

				$date_range = $this->PC->get_credit_period( $day_of_month, $offset, $term['term_id'] );
				$date_from = $date_range['from'];
				$date_to = $date_range['to'];
			}
			
			$topup = $this->PC->get_user_credit_topup( $customer['id'], $date_from, $date_to );
			$topup = ( $topup )? $topup : 0;
			$total_creditable = $credits + $topup;
			
			$tki_salary = $tki['betrg'] - $tki['deduct'];
			if( $customer['topup_percent'] > 0 )
				$percent = $customer['topup_percent'];
			else if( $customer['topup_percent'] <= 0 && $customer['gtopup_percent'] > 0 )
				$percent = $customer['gtopup_percent'];
			else
				$percent = ( $opts['percentage'] > 0 )? $opts['percentage'] : 50;
			
			$planned_creditable = 0;
			$now_topup = 0;
			if( $tki_salary > 0 )
			{
				$planned_creditable = round_to( $tki_salary / 100 * $percent, 2 );
				$now_topup = ( $planned_creditable > $total_creditable )? $planned_creditable - $total_creditable : 0;
			}
			else
			{
				$planned_creditable = $tki_salary;
				//$now_topup = $planned_creditable;
			}
			
			if( $now_topup != 0 )
			{
				$datas = [
					//'customer_id' => $customer['id'],
					'customer_code' => $customer['code'],
					'credit_limit' => $now_topup,
					'effective_from' => current_time( 'mysql' ),
					'percentage' => 100,
				];
				if( defined( 'developer_debug' ) ) pd($datas);
				$this->automate = true;
				$result = $this->action_handler( 'save', $datas );
				$this->automate = false;
				if( ! $result['succ'] )
                {
                    $succ = false;
                    //$this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
                }
			}
		}

		public function allow_api_request( $status = true, $url = '', $args = [], $url_host = '' )
		{
			return true;
		}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Credit Topup" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Credit Topup"
				>
					<?php echo $actions['save'] ?> Credit Topup
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = $this->Logic->get_infos( $filters, [], true, [ 'term'=>1 ] );
			if( $datas )
			{
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/creditTopup-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/creditTopup-form.php', $args );
		}
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/creditTopupListing.php" ); 
			$Inst = new WCWH_CreditTopup_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$Inst->filters = $filters;
			$Inst->advSearch_onoff();
			
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'term'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}