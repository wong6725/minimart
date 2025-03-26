<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Client_Class" ) ) include_once( WCWH_DIR . "/includes/classes/client.php" ); 
if ( !class_exists( "WCWH_Addresses_Class" ) ) include_once( WCWH_DIR . "/includes/classes/addresses.php" ); 

if ( !class_exists( "WCWH_Client_Controller" ) ) 
{

class WCWH_Client_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_client";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Client_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newClient',
	);

	public $useFlag = false;

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
		$this->Logic = new WCWH_Client_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;

		$this->Detail = new WCWH_Addresses_Class( $this->db_wpdb );
		$this->Detail->set_section_id( $this->section_id );
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

		//$this->Logic->setWarehouse( $this->warehouse );
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_defaultFields()
	{
		return array(
			'comp_id' => 0,
			'client_no' => '',
			'code' => '',
			'tin' => '',
			'name' => '',
			'id_type' => '',
			'id_code' => '',
			'sst_no' => '',
			'parent' => 0,
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_defaultDetailFields()
	{
		return array(
			'ref_type' => '',
			'ref_id' => 0,
			'addr_type' => '',
			'address_1' => '',
			'address_2' => '',
			'country' => '',
			'state' => '',
			'city' => '',
			'postcode' => '',
			'contact_person' => '',
			'contact_no' => '',
			'status' => 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	protected function get_uniqueFields()
	{
		return array(
			'code'
		);
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
				case 'save':
				case 'update':
					if($datas['tin'])
					{
						$string = strtoupper( $datas['tin'] );  // Example input

						$prefixes = ["C", "CS", "D", "F", "FA", "PT", "TA", "TC", "TN", "TR", "TP", "J", "LE", 'IG', 'OG', 'SG'];

						if (substr($string, -1) !== "0")
						{
							$succ = false;
							$this->Notices->set_notice( 'TIN number should end with a zero (0)', 'error' );
						}
						else
						{
							$matches = false;
							foreach ($prefixes as $prefix)
							{
								if (substr($string, 0, strlen($prefix)) === $prefix)
								{
									$matches = true;
									if (substr($string, strlen($prefix), 1) === "0")
									{
										$string = substr($string, 0, strlen($prefix)) . substr($string, strlen($prefix) + 1);
									}
									break;
								}
							}

							if (!$matches) 
							{
								$succ = false;
								$this->Notices->set_notice( 'TIN number start with invalid prefix', 'error' );
							}
							else 
							{
								$datas['tin'] = $string;
							}
						}
					}

					if( $action == 'update' && (! isset( $datas['id'] ) || ! $datas['id'] ))
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
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
        	$child_result = array();
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

					$billing = $datas['billing']; unset( $datas['billing'] );
					$shipping = $datas['shipping']; unset( $datas['shipping'] );

					if( !empty( $datas['tin'] ) )
					{
						$datas['tin'] = strtoupper( $datas['tin'] );
						if( ( substr($datas['tin'], 0, 2) === "IG" || substr($datas['tin'], 0, 2) === "OG" || substr($datas['tin'], 0, 2) === "SG" ) )
							$datas['tin'] = "IG" . substr($datas['tin'], 2); 
					}
					
					if( ! $datas['code'] )
					{
						if( $datas[ $this->get_primaryKey() ] )
						{
							$scode = get_supplier_meta( $datas[ $this->get_primaryKey() ], 'scode', true );
							$datas['code'] = ( $scode )? $scode : $datas['code'];
							
							if( ! $datas['code'] )
							{
								$exist = $this->Logic->get_infos( [ $this->get_primaryKey() => $datas[ $this->get_primaryKey() ] ], [], true );
								$datas['code'] = ( $exist['code'] )? $exist['code'] : $datas['code'];
							}
						}
						$this->temp_data = $datas;
							if( empty( $datas['code'] ) )
							{
								$datas['scode'] = apply_filters( 'warehouse_generate_docno', $datas['code'], $this->section_id );
								$datas['code'] = $datas['scode'];
							}
						$this->temp_data = array();
					}

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					if( ! $datas[ $this->get_primaryKey() ] && $action == 'save' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
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

						if( ! $metas['no_metric_sale'] ) $metas['no_metric_sale'] = '';
						if( ! $metas['no_returnable_handling'] ) $metas['no_returnable_handling'] = '';
						if( $datas['parent'] == $datas[ $this->get_primaryKey() ] ) $datas['parent'] = 0;
					}

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

						    $addr_ids = array();
						    $exists = $this->Detail->get_infos( [ 'ref_type'=>$this->section_id, 'ref_id'=>$result['id'] ] );
						    if( $exists )
							{
								foreach( $exists as $exist )
								{	
									$addr_ids[] = $exist['id'];
								}
							}

							if( $billing && ( 
								!empty( trim( $billing['contact_person'] ) ) 
								|| !empty( trim( $billing['contact_no'] ) )
								|| !empty( trim( $billing['address_1'] ) ) 
							) )
							{
								$billing['ref_type'] = $this->section_id;
								$billing['ref_id'] = $result['id'];
								$billing['addr_type'] = 'billing';

								$billing['lupdate_by'] = $user_id;
								$billing['lupdate_at'] = $now;

								if( ! $billing['id'] || ! in_array( $billing['id'], $addr_ids ) )	//save
								{
									$billing['created_by'] = $user_id;
									$billing['created_at'] = $now;
									unset($billing['id']);

									$billing = wp_parse_args( $billing, $this->get_defaultDetailFields() );
									
									$child_result = $this->Detail->action_handler( 'save', $billing );
								}
								else if( $billing['id'] && in_array( $billing['id'], $addr_ids ) )	//update
								{
									$child_result = $this->Detail->action_handler( 'update', $billing );
								}

								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}

								if( empty( trim( $shipping['address_1'] ) ) ) $shipping['address_1'] = $billing['address_1'];
							}

							if( $shipping && ( 
								!empty( trim( $shipping['contact_person'] ) ) 
								|| !empty( trim( $shipping['contact_no'] ) )
								|| !empty( trim( $shipping['address_1'] ) ) 
							) )
							{
								$shipping['ref_type'] = $this->section_id;
								$shipping['ref_id'] = $result['id'];
								$shipping['addr_type'] = 'shipping';

								$shipping['lupdate_by'] = $user_id;
								$shipping['lupdate_at'] = $now;

								$shipping['country'] = ( $shipping['country'] )? $shipping['country'] : $billing['country'];
								$shipping['state'] = ( $shipping['state'] )? $shipping['state'] : $billing['state'];
								$shipping['city'] = ( $shipping['city'] )? $shipping['city'] : $billing['city'];
								$shipping['postcode'] = ( $shipping['postcode'] )? $shipping['postcode'] : $billing['postcode'];
								$shipping['contact_person'] = ( $shipping['contact_person'] )? $shipping['contact_person'] : $billing['contact_person'];
								$shipping['contact_no'] = ( $shipping['contact_no'] )? $shipping['contact_no'] : $billing['contact_no'];

								if( ! $shipping['id'] || ! in_array( $shipping['id'], $addr_ids ) )	//save
								{
									$shipping['created_by'] = $user_id;
									$shipping['created_at'] = $now;
									unset($shipping['id']);

									$shipping = wp_parse_args( $shipping, $this->get_defaultDetailFields() );
									
									$child_result = $this->Detail->action_handler( 'save', $shipping );
								}
								else if( $shipping['id'] && in_array( $shipping['id'], $addr_ids ) )	//update
								{
									$child_result = $this->Detail->action_handler( 'update', $shipping );
								}

								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Client" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Client"
				>
					<?php echo $actions['save'] ?> Client
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
			'hook' 		=> $this->section_id.'_form',
			'action' 	=> 'save',
			'token' 	=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

			$datas = $this->Logic->get_infos( $filters, [], true, [ 'parent'=>1, 'company'=>1 ] );
			if( $datas )
			{
				$metas = get_client_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$filters = [ 'ref_type'=>$this->section_id, 'ref_id'=>$id ];
				if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
				
				$addrs = $this->Detail->get_infos( $filters, [], false );
				if( $addrs )
				{
					foreach( $addrs as $addr )
					{
						$datas[ $addr['addr_type'] ] = $addr;
					}
					
				}

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/client-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/client-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/clientListing.php" ); 
			$Inst = new WCWH_Client_Listing();
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

			$datas = $this->Logic->get_infos( $filters, $order, false, [ 'parent'=>1, 'company'=>1, 'tree'=>1 ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}