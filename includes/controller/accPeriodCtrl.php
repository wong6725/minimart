<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_AccPeriod_Class" ) ) include_once( WCWH_DIR . "/includes/classes/acc-period.php" ); 
if ( !class_exists( "WCWH_StockMovementWA_Class" ) ) require_once( WCWH_DIR . "/includes/classes/stock-movement-wa.php" );

if ( !class_exists( "WCWH_AccPeriod_Controller" ) ) 
{

class WCWH_AccPeriod_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_acc_period";

	public $Notices;
	public $className = "AccPeriod_Controller";

	public $Logic;
	public $SM;

	public $tplName = array(
		'new' => 'newAccPeriod',
		'row' => 'rowAccPeriod',
	);

	public $useFlag = false;

	protected $warehouse = array();


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
		$this->Logic = new WCWH_AccPeriod_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;

		$this->SM = new WCWH_StockMovementWA_Class();
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function get_unneededFields()
	{
		return array( 
			'action', 
			'token', 
			'wh', 
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
					if( ! $datas['header'] || ! $datas['header']['warehouse_id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'delete':
				case 'approve':
				case 'reject':
				case "close":
				case "reopen":
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

	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
				case "update":
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['doc_date'] = ( $header['doc_date'] )? date( 'Y-m-t 23:59:59', strtotime( $header['doc_date'] ) ) : current_time( 'Y-m-t 23:59:59' );
					$header['doc_time'] = ( $header['doc_time'] )? date( ' H:i:s', strtotime( $header['doc_time'] ) ) : " 23:59:59";

					$header['warehouse_id'] = !empty( $header['warehouse_id'] )? $header['warehouse_id'] : $this->warehouse['code'];

					$f = [ 'warehouse_id'=>$header['warehouse_id'], 'doc_date'=>$header['doc_date'] ];
					if( $header['doc_id'] ) $f['not_doc_id'] = $header['doc_id'];

					$exists = $this->Logic->get_header( $f, [], true, [ 'usage'=>1 ] );
					if( $exists )
					{
						$succ = false;
						$this->Notices->set_notice( 'Same Closing Date Found '.$exists['docno'], 'error' );
					}
					
					if( $succ )
					{
						$result = $this->Logic->child_action_handle( $action, $header, $detail );
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];
							$count_succ++;

							if( $action == 'save' )
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
				case "close":
				case "reopen":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							if( in_array( $action, ['close','reopen'] ) && ! empty( $datas['remark'] ) ) $header['remark'] = $datas['remark'];
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
								$count_succ++;

								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
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
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );

					if( $ids )
					{
						foreach( $ids as $id )
						{
							$succ = apply_filters( 'wcwh_todo_external_action', $id, $this->section_id, $action, ( $datas['remark'] )? $datas['remark'] : '' );
							if( $succ )
							{
								$status = apply_filters( 'wcwh_get_status', $action );

								$header = [];
								$header['doc_id'] = $id;
								$header['flag'] = 0;
								$header['flag'] = ( $status > 0 )? 1 : ( ( $status < 0 )? -1 : $header['flag'] );
								$result = $this->Logic->child_action_handle( $action, $header );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
								}

								if( $succ )
								{
									$outcome['id'][] = $result['id'];
									$count_succ++;

									//Doc Stage
									$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
									    'ref_type'	=> $this->section_id,
									    'ref_id'	=> $result['id'],
									    'action'	=> $action,
									    'status'    => $status,
									    'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
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
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	//if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		if( ! $this->after_action( $succ, $outcome['id'], $action ) )
           			$succ = false;
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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false, [] );
			$handled = [];
			foreach( $exists as $exist )
			{
				$handled[ $exist['doc_id'] ] = $exist;
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

				$wh = $handled[ $ref_id ]['warehouse_id'];
				if( $handled[ $ref_id ]['status'] >= 3 && $wh )
				{
					$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $wh );
					if( ! $succ )
					{
						$this->Notices->set_notice( 'arrange-fail', 'error' );
					}
				}

				if( $succ && in_array( $action, ['close'] ) )
				{
					if ( !class_exists( "WCWH_SM_Rectify_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/smRectifyCtrl.php" ); 
					$Inst = new WCWH_SM_Rectify_Controller();
					$Inst->disable_rectify( $ref_id );

					$succ = $this->SM->stock_movement_handler( $handled[ $ref_id ]['warehouse_id'], $ref_id, $handled[ $ref_id ] );
					
					if( ! $succ ) break;
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
		$default_column = [];

		$default_column['header'] = [ 'warehouse_id', 'docno', 'sdocno', 'doc_date', 'post_date', 'doc_type', 'status', 'flag', 'remark' ];

		$default_column['detail'] = [];

		$default_column['title'] = array_merge( $default_column['header'], $default_column['detail'] );
		$default_column['default'] = array_merge( $default_column['header'], $default_column['detail'] );

		$default_column['unchange'] = [ 'doc_id' ];

		return $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		$columns = $this->im_ex_default_column();

		$raws = $this->Logic->get_export_data( $params );

		$cols = array_merge( $columns['header'], $columns['detail'] );

		$datas = [];
		foreach( $raws as $i => $row )
		{
			$line = [];
			foreach( $cols as $col )
			{
				$line[ $col ] = $row[ $col ];
				$datas[$i] = $line;
			}
		}

		return $datas;
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();

		$header_col = $columns['header'];
		$detail_col = $columns['detail'];
		$unchange_col = $columns['unchange'];

		//$datas = $this->seperate_import_data( $datas, $header_col, [ 'sdocno' ], $detail_col );
		if( $datas )
		{	
			foreach( $datas as $i => $data )
			{
				$exists = $this->Logic->get_header( [ 'sdocno' => $data['sdocno'] ], [], true );
				if( ! $exists || $exists['status'] <= 0 )
				{
					$header = [
						'warehouse_id' => $data['warehouse_id'],
						'docno' => $data['docno'],
						'sdocno' => $data['sdocno'],
						'doc_date' => $data['doc_date'],
						'hflag' => $data['flag'],
					];
					$dat = [ 'header' => $header, 'detail' => [] ];
					$outcome = $this->action_handler( 'save', $dat, $data );
					if( ! $outcome['succ'] ) 
					{
						$succ = false;
						break;
					}
				}
				
				$exists = $this->Logic->get_header( [ 'sdocno' => $data['sdocno'] ], [], true );
				if( $exists && $exists['status'] > 0 )
				{
					$dat = [ 'id'=>$exists['doc_id'] ];
					if( $data['remark'] ) $dat['remark'] = $data['remark'];
					if( $data['status'] == 10 ) $action = 'close';
					else if( $data['status'] == 3 ) $action = 'reopen';

					if( $action )
					{
						$outcome = $this->action_handler( $action, $dat, [], false );
						if( ! $outcome['succ'] ) 
						{
							$succ = false;
							break;
						}
					}
					else
						$succ = false;
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['close'] ?> Account Period" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="<?php echo $actions['close'] ?> Account Period"
				>
					<?php echo $actions['close'] ?> Account Period
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'get_content' => $getContent,
			'wh_code'	=> $this->warehouse['code'],
		);

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [ 'created_at'=>'DESC' ], false, [ 'uom'=>1 ] );

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();

				if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['status'] = ( $item['status'] > 0 )? 'Opened' : 'Closed';
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				if( $datas['details'] )
				{
					$args['render'] = $Inst->get_listing( [
			        		'num' => '',
			        		'created_at' => 'Date',
			        		'status' => 'Status',
			        		'dremark' => 'Remark',
			        	], 
			        	$datas['details'], 
			        	[], 
			        	[], 
			        	[ 'off_footer'=>true, 'list_only'=>true ]
			        );
				}
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/accPeriod-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/accPeriod-form.php', $args );
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
			include_once( WCWH_DIR . "/includes/listing/accPeriodListing.php" ); 
			$Inst = new WCWH_AccPeriod_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$wh = ( $this->warehouse['code'] )? $this->warehouse['code'] : '';
			$count = $this->Logic->count_statuses( $wh );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : ( $count[ 'process' ]? 'process' : 'all' );

			if( $this->warehouse['code'] ) $filters['warehouse_id'] = $this->warehouse['code'];
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			if( ! $order ) $order = ['a.warehouse_id'=>'ASC','a.doc_date'=>'DESC'];

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>['remark'] ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}