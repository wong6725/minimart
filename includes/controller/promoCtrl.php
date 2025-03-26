<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_PromoHeader' ) ) include_once( WCWH_DIR . "/includes/classes/promo-header.php" ); 
if( ! class_exists( 'WCWH_PromoDetail' ) ) include_once( WCWH_DIR . "/includes/classes/promo-detail.php" ); 

if ( !class_exists( "WCWH_Promo_Controller" ) ) 
{

class WCWH_Promo_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_promo";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Promo_Controller";

	public $Logic;
	public $Ref;
	public $Detail;

	public $custom_columns = [];

	public $tplName = array(
		'new' => 'newPromo',
		'rowCond' => 'rowCond',
		'rowRule' => 'rowRule',
		'import' => 'importPromo',
		'export' => 'exportPromo',
	);

	public $promo_type = [
		"condition" => "Condition",
		"rule"		=> "Promo Rule",
	];

	public $promo_cond_match = [
		"amount" 	=> [ "title" => "Order Amount", "hasItem" => 0 ],
		"item"		=> [ "title" => "Order Item", "hasItem" => 1 ],
	];

	public $promo_rule_match = [
		"item"		=> [ "title" => "FOC Item", "hasItem" => 1 ],
		"item_amount"	=> [ "title" => "Item Amount", "hasItem" => 1 ],
		"item_discount"	=> [ "title" => "Item Discount %", "hasItem" => 1 ],
		"amount"	=> [ "title" => "Order Amount", "hasItem" => 0 ],
		"discount"	=> [ "title" => "Order Discount %", "hasItem" => 0 ],
	];

	public $useFlag = false;

	private $temp_data = array();

	protected $import_data = array();

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();

		$this->set_logic();
	}

	public function __destruct() {}

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
		$this->Logic = new WCWH_PromoHeader( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;

		$this->Detail = new WCWH_PromoDetail( $this->db_wpdb );
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
			'docno' => '',
			'sdocno' => '',
			'seller' => '',
			'title' => '',
			'remarks' => '',
			'cond_type' => '',
			'from_date' => '',
			'to_date' => '',
			'limit' => 0,
			'used' => 0,
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
				'promo_id' => 0,
				'type' => '',
				'match' => '',
				'product_id' => 0,
				'amount' => 0,
				'status' => 1,
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
				case 'save':
					if( ! $datas['condition'] || ! $datas['rule'] )
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

	public function action_handler( $action = 'save', $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		
		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$promo_id = 0;
        	$result = array();
        	$child_result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$header = $datas['header'];
					$header = $this->data_sanitizing( $header );
					
					$header = ( $header )? $header : $datas['header'];

					$detail = $datas['detail'];
					if( ! $datas['detail'] )
					{
						$detail = $datas['condition'];
						if( $datas['rule'] ) foreach( $datas['rule'] as $i => $rule ) $detail[] = $rule;
					}
					
					$header['lupdate_by'] = $user_id;
					$header['lupdate_at'] = $now;

					$extracted = $this->extract_data( $header );
					$header = $extracted['datas'];
					$metas = $extracted['metas'];

					$header['limit'] = ( $header['limit'] )? $header['limit'] : 0;
					
					$source = [];
					if( ! $header[ $this->get_primaryKey() ] && $action == 'save' )
					{
							
						$sdocno = "Promo".get_current_time('YmdHis');
        				$header['sdocno'] =	empty( $header['sdocno'] )? apply_filters( 'warehouse_generate_docno', $sdocno, $this->section_id ) : $header['sdocno'];
						$header['docno'] = empty( $header['docno'] ) ? $header['sdocno'] : $header['docno'];

						$header['created_by'] = $user_id;
						$header['created_at'] = $now;

						$header = wp_parse_args( $header, $this->get_defaultFields() );
						
						$isSave = true;
						$result = $this->Logic->action_handler( $action, $header, $metas );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'error', 'error' );
						}
						else
						{
							$outcome['id'][] = $result['id'];
							$promo_id = $result['id'];
						}

						if( $succ && $detail )
						{
							foreach( $detail as $i => $row )
							{
								unset( $row['item_id'] );
								$row['promo_id'] = $promo_id;

								$row = wp_parse_args( $row, $this->get_defaultDetailFields() );

								$child_result = $this->Detail->action_handler( 'save', $row );
								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}
							}
						}
					}
					else if( isset( $header[ $this->get_primaryKey() ] ) && $header[ $this->get_primaryKey() ] ) //update
					{	
						$result = $this->Logic->action_handler( $action, $header, $metas );

						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'error', 'error' );
						}
						else
						{
							$outcome['id'][] = $result['id'];
							$promo_id = $result['id'];

							if( ! $metas['once_per_order'] ) delete_promo_header_meta( $promo_id, 'once_per_order' );
						}

						$item_ids = [];
						$exists = $this->Detail->get_infos( [ 'promo_id'=>$promo_id, 'status'=>1 ] );
						if( $exists )
						{
							foreach( $exists as $exist )
							{	
								$item_ids[] = $exist['id'];
							}
						}

						if( $succ && $detail )
						{
							$items = array();
							foreach( $detail as $i => $row )
							{
								$row['promo_id'] = $promo_id;

								$row['id'] = $row['item_id'];
								unset( $row['item_id'] );

								if( ! $row['id'] || ! in_array( $row['id'], $item_ids ) )		//save
								{
									$row = wp_parse_args( $row, $this->get_defaultDetailFields() );

									$child_result = $this->Detail->action_handler( 'save', $row );
								}
								else if( $row['id'] && in_array( $row['id'], $item_ids ) )	//update
								{
									$child_result = $this->Detail->action_handler( 'update', $row );
								}

								if( ! $child_result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
									break;
								}

								if( $child_result['id'] ) $items[] = $child_result['id'];
							}

							//remove unneeded row
							if( $item_ids && $items )
							{
								foreach( $item_ids as $id )
								{
									if( ! in_array( $id, $items ) )
									{
										$child_result = $this->Detail->action_handler( 'delete', [ 'id' => $id ] );

										if( ! $child_result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'error', 'error' );
											break;
										}
									}
								}
							}
						}
					}

					if( $succ )
					{
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
				break;
				case "delete":
				case "restore":
					$datas['lupdate_by'] = $user_id;
					$datas['lupdate_at'] = $now;

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

							if( $succ ) //delete details
							{
								$outcome['id'][] = $result['id'];

								$args = [ 'promo_id'=>$id ];
								if( $action == 'delete' ) $args['status'] = 1;
								$exists = $this->Detail->get_infos( $args );
								if( $exists )
								{
									foreach( $exists as $i => $row )
									{
										$child_result = $this->Detail->action_handler( $action, [ 'id'=>$row['id'] ] );
										if( ! $child_result['succ'] )
										{
											$succ = false;
											$this->Notices->set_notice( 'error', 'error' );
											break;
										}
									}
								}
							}

							if( $succ )
							{
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
					$datas['lupdate_by'] = $user_id;
					$datas['lupdate_at'] = $now;

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
				case "import":
					$files = $this->files_grouping( $_FILES['import'] );
					if( $files )
					{
						$succ = $this->import_data( $files, $datas );
					}
					else
					{
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
				break;
				case "export":
					$datas['filename'] = 'Promo list ';

					$params = [];
					$params['sellers'] = $datas['sellers'];
					
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					//if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];

					$date = current_time( 'Y-m-d' );
					if( $datas['on_date'] ) $datas['filename'].= date( 'Y-m-d', strtotime( $date ) );

					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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

			$exists = $this->Logic->get_infos( [ 'id' => $id ], [], false );
			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['id'] ] = $exist;
			}
			
			foreach( $id as $ref_id )
			{
				if( $handled[ $ref_id ]['flag'] == 0 )
				{
					$succ = apply_filters( 'wcwh_todo_arrangement', $ref_id, $this->section_id, $action );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}

				if( $handled[ $ref_id ]['flag'] && $handled[ $ref_id ]['seller'] )
				{
					$seller = $handled[ $ref_id ]['seller'];
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $seller );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
						break;
					}
				}
			}
		}

		return $succ;
	}


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		$default_column['header'] = [ 
			'Docno', 'Sdocno', 'Seller', 'Title', 'Remarks', 'Cond Type', 'From Date', 'To Date', 'Limit', 'Used', 'Status', 'Flag', 
			'Limit Type', 'Rule Type', 'Once per Order' 
		];
		$default_column['detail'] = [ 'Item', 'Code', 'Barcode', 'Type', 'Match', 'Amount' ];
		$default_column['title'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['header'] = [ 
			'docno', 'sdocno', 'seller', 'title', 'remarks', 'cond_type', 'from_date', 'to_date','limit', 'used', 'status', 'flag', 
			'limit_type', 'rule_type', 'once_per_order'
		];
		$default_column['detail'] = [ 'name', 'code', 'serial', 'type', 'match', 'amount' ];
		$default_column['default'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['unique'] = array( 'serial' );
		$default_column['required'] = array( 'amount' );

		return ( $this->custom_columns )? $this->custom_columns : $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		$type = $params['export_type']; unset( $params['export_type'] );
		switch( $type )
		{
			case '':
			default:
				return $this->Logic->get_export_data( $params );
			break;
		}
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();
		
		$unique = $columns['unique'];
		$unchange = $columns['unchange'];
		$required = $columns['required'];

		$header_col = $columns['header'];
		$detail_col = $columns['detail'];

		$datas = $this->seperate_import_data( $datas, $header_col, [ 'sdocno' ], $detail_col );
		if( $datas )
		{
			wpdb_start_transaction();
			
			foreach( $datas as $i => $data )
			{
				if( !empty( $unchange ) )
				{
					foreach( $unchange as $key )
					{
						unset( $data['header'][$key] );
						unset( $data['detail'][$key] );
					}
				}
				
				foreach( $data['detail'] as $j => $row )
				{
					//validation
					if( !empty( $required ) )
					{
						$hasEmpty = false;
						foreach( $required as $key )
						{
							if( empty( $row[ $key ] ) ) $hasEmpty = true;
						}
						if( $hasEmpty )
						{
							$this->Notices->set_notice( 'Data missing required fields', 'error' );
							$succ = false;
							break;
						}
					}
					if( ! $succ ) break;

					$curr = [];
					if( !empty( $unique ) )
					{
						foreach( $unique as $key )
						{
							if( ! empty( $row[ $key ] ) )
							{
								$found = apply_filters( 'wcwh_get_item', [ $key=>$row[ $key ] ], [], true, [] );
								if( $found )
								{
									$curr = $found;
									break;
								}
							}
						}
					}
					if( $curr )
					{
						$data['detail'][$j]['product_id'] = $curr['id'];
					}

					unset( $data['detail'][$j]['name'] ); 
					unset( $data['detail'][$j]['code'] );
					unset( $data['detail'][$j]['serial'] );
				}
				
				//pd($data);exit;
				if( $succ )
				{
					$exists = $this->Logic->get_infos( [ 'sdocno' => $data['header']['sdocno'] ], [], true );
					if( $exists )
					{
						$data['header']['id'] = $exists['id'];

						if( $data['header']['status'] > 0 && $exists['status'] > 0 )
						{
							$outcome = $this->action_handler( 'update', $data, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
						}
						else if( $data['header']['status'] > 0 && $exists['status'] <= 0 )
						{
							$dat = [ 'id' => $exists['id'] ];
							$outcome = $this->action_handler( 'restore', $dat, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
						}
						else if( $data['header']['status'] <= 0 && $exists['status'] > 0 )
						{
							$dat = [ 'id' => $exists['id'] ];
							$outcome = $this->action_handler( 'delete', $dat, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
						}
					}
					else
					{
						if( $data['header']['status'] > 0 )
						{
							$outcome = $this->action_handler( 'save', $data, [], false );
							if( ! $outcome['succ'] ) 
							{
								$succ = false;
								break;
							}
						}
					}
				}
			}
			
			wpdb_end_transaction( $succ );
		}

		if( ! $succ )
			$this->Notices->set_notice( 'Import Failed', 'error' );

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
					data-title="<?php echo $actions['save'] ?> Promo" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Promo"
				>
					<?php echo $actions['save'] ?> Promo
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?>"
				>
					<i class="fa fa-upload" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Promo" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Pos Foc"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = true )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowCondTpl'	=> $this->tplName['rowCond'],
			'rowRuleTpl'	=> $this->tplName['rowRule'],
			'promo_type'	=> $this->promo_type,
			'promo_cond_match'	=> $this->promo_cond_match,
			'promo_rule_match'	=> $this->promo_rule_match,
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];
		
		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			if( $datas )
			{
				$metas = get_promo_header_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$filters = [ 'promo_id'=>$id ];
				if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

				$arg = [ 'item'=>1, 'usage'=>1 ];
				$datas['details'] = $this->Detail->get_infos( $filters, [], false, $arg );
				
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();
		        	
		        if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i] = $item;
		        		$datas['details'][$i]['num'] = ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'type' => 'Type',
		        		'match' => 'Match',
		        		'prdt_name' => 'Item',
		        		'amount' => 'Amt/Qty',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/promo-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/promo-form.php', $args );
		}
	}

	public function view_row()
	{
		$args = [ 'type'=>'condition', 'prefixName'=>'_condition' ];
		do_action( 'wcwh_templating', 'segment/promo-row.php', $this->tplName['rowCond'], $args );
		$args = [ 'type'=>'rule', 'prefixName'=>'_rule' ];
		do_action( 'wcwh_templating', 'segment/promo-row.php', $this->tplName['rowRule'], $args );
	}

	public function import_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'import',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['import'],
		);

		do_action( 'wcwh_templating', 'import/import-promo.php', $this->tplName['import'], $args );
	}

	public function export_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['export'],
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		do_action( 'wcwh_templating', 'export/export-promo.php', $this->tplName['export'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/promoListing.php" ); 
			$Inst = new WCWH_Promo_Listing();
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

			$datas = $this->Logic->get_infos( $filters, $order, false, [], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}