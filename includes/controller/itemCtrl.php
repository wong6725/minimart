<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Item_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item.php" ); 

if ( !class_exists( "WCWH_ItemRel_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/itemRelCtrl.php" ); 

if ( !class_exists( "WCWH_Item_Controller" ) ) 
{

class WCWH_Item_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_items";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Item_Controller";

	public $Logic;

	public $Rel;

	public $tplName = array(
		'new' => 'newItem',
		'import' => 'importItem',
		'export' => 'exportItem',
		'row' => 'rowGtin',
	);

	public $useFlag = false;

	private $temp_data = array();
	
	private $unique_field = array( 'name', '_sku' );

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		if( $wh ) $this->set_warehouse( $wh );

		$this->arrangement_init();
		
		$this->set_logic();

		add_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10, 2 );
	}
	
	public function __destruct() 
	{
        remove_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10 );
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
		$this->Logic = new WCWH_Item_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;

		$this->Rel = new WCWH_ItemRel_Controller();
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
			'name' => '',
			'_sku' => '',
			'code' => '',
			'serial' => '',
			'serial2' => '',
			'product_type' => 'simple',
			'desc' => '',
			'_material' => '',
			'_stock_out_type' => 2,
			'_uom_code' => '',
			'_self_unit' => 0,
			'_content_uom' => '',
			'_parent_unit' => 0,
			'_tax_status' => '',
			'_tax_class' => '',
			'_manage_stock' => 'no',
			'_backorders' => 'yes',
			'grp_id' => 0,
			'store_type_id' => 0,
			'category' => 0,
			'parent' => 0,
			'ref_prdt' => 0,
			'_thumbnail_id' => 0,
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
		return $this->unique_field;
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
				if( empty( $datas[ $key ] ) ) continue;

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

	public function docno_replacer( $sdocno, $doc_type = '' )
	{
		if( $doc_type && $doc_type == $this->section_id )
		{	
			$datas = $this->temp_data;
			$ref = array();
			
			if( $datas['grp_id'] )
			{
				$ref = apply_filters( 'wcwh_get_item_group', [ 'id'=>$datas['grp_id'] ], [], true, [] );
			}
			
			$find = [ 
				'GrpCode' => '{GrpCode}',
			];

			$replace = [ 
				'GrpCode' => ( $ref['prefix'] )? $ref['prefix'] : '',
			];

			$sdocno = str_replace( $find, $replace, $sdocno );
		}

		return $sdocno;
	}

	protected function generate_serial()
	{
		$isUnique = true;
		do 
		{
			$serial = apply_filters( 'warehouse_generate_docno', '', 'item_serial' );
			$result = $this->Logic->get_infos( [ 'serial' => $serial ], [], true );
			if( $result ) 
				$isUnique = false;
			else
				$isUnique = true;
		} while( ! $isUnique ); 

		return $serial;
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
					$datas['inconsistent_unit'] = ( $datas['inconsistent_unit'] )? 1 : 0;
					
					if( $datas['inconsistent_unit'] ) $datas['_parent_unit'] = 0;
					if( $datas['_self_unit'] ) $datas['_self_unit'] = 1;

					if( ! $datas['code'] )
					{
						if( $datas[ $this->get_primaryKey() ] )
						{
							$scode = get_items_meta( $datas[ $this->get_primaryKey() ], 'scode', true );
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
						else
						{
							$datas['code'] = apply_filters( 'warehouse_renew_docno', $datas['code'], $this->section_id );
							if( $scode && $scode !== $datas['code'] )
								$datas['scode'] = $datas['code'];
						}
						$this->temp_data = array();
					}
					else
					{
						$scode = get_items_meta( $datas[ $this->get_primaryKey() ], 'scode', true );
						$this->temp_data = $datas;
						$datas['code'] = apply_filters( 'warehouse_renew_docno', $datas['code'], $this->section_id );
						if( $scode && $scode !== $datas['code'] )
							$datas['scode'] = $datas['code'];
						$this->temp_data = array();
					}

					$reorder_type = $datas['reorder_type'];
					unset($datas['reorder_type']);

					$datas['serial2'] = array_filter( $datas['serial2'] );
					if( ! empty( $datas['serial2'] ) )
					{
						$has_serial = $this->Logic->check_serial2_unique( $datas['serial2'], $datas[ $this->get_primaryKey() ] );
						if( $has_serial )
						{
							$succ = false;
							$this->Notices->set_notice( 'not-unique', 'error' );
						}
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

						$datas['serial'] = !empty( $datas['serial'] )? $datas['serial'] : $this->generate_serial();

						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
						$isSave = true;
					}

					if( $datas[ $this->get_primaryKey() ] && $action == 'update' )
					{
						if( ! $this->validate_unique( $action, $datas ) )
						{
							$succ = false;
						}
						
						if( ! $datas['_sku'] ) $datas['_sku'] = '';
						if( $datas['parent'] == $datas[ $this->get_primaryKey() ] ) $datas['parent'] = 0;

						//--------22/11/2022 Repleaceable
						$is_returnable = get_items_meta( $datas[ $this->get_primaryKey() ], 'is_returnable', true );
						$metas['is_returnable'] = ( $metas['is_returnable'] )? 1 : ( $is_returnable? $is_returnable : 0 );
						
						//show calculation for returned empty gt
						$calc_egt = get_items_meta( $datas[ $this->get_primaryKey() ], 'calc_egt', true );
						$metas['calc_egt'] = ( $metas['calc_egt'] )? 1 : ( $calc_egt? $calc_egt : 0 );

						$add_gt_total = get_items_meta( $datas[ $this->get_primaryKey() ], 'add_gt_total', true );
						$metas['add_gt_total'] = ( $metas['add_gt_total'] )? 1 : ( $add_gt_total? $add_gt_total : 0 );
					}
					
					$datas = $this->json_encoding( $datas );

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
							$datas['id'] = $result['id'];
							$wc = $this->Logic->woocommerce_product_handler( $action, $datas, $obj );
							if( ! $wc['succ'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'error', 'error' );
							}
						}

						if( $succ && isset( $reorder_type ) )
						{
							$rel = [
								'items_id' => $result['id'],
								'rel_type' => 'reorder_type',
								'reorder_type' => $reorder_type,
								'wh_id' => $this->warehouse['code'],
							];
							$res = $this->Rel->action_handler( $action, $rel );
							 if( ! $res['succ'] )
			                {
			                    $succ = false;
			                    $this->Notices->set_notices( $Inst->Notices->get_operation_notice() );
			                }
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
								$datas['id'] = $result['id'];
								$wc = $this->Logic->woocommerce_product_handler( $action, $datas, $obj );
								if( ! $wc['succ'] )
								{
									$succ = false;
									$this->Notices->set_notice( 'error', 'error' );
								}
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
					$datas['filename'] = 'item';

					$params = [];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					$params['status'] = $datas['status'];

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

			$exists = $this->Logic->get_infos( [ 'id'=>$id ], [], false );
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
				
				$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['code'] );
				if( ! $succ )
				{
					$this->Notices->set_notice( 'arrange-fail', 'error' );
				}

				if( $handled[ $ref_id ]['_thumbnail_id'] )
				{
					$url = wp_get_attachment_image_url( $handled[ $ref_id ]['_thumbnail_id'], 'medium' );
					update_items_meta( $ref_id, '_thumbnail_url', $url );
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

		$default_column['title'] = array( 'Name' , 'Print Name','Short name','Chinese Name','Indo Name', 'GTIN', 'Code', 'Barcode', 'Addon Code', 'Base Item','Item UOM', 'Content UOM', 'Item Unit', 'Content Unit', 'Inconsistent', 'Stock by Metric', 'Group', 'Category', 'StockOut', 'Scale Key', 'Sellable', 'Length(cm)', 'Height(cm)', 'Width(cm)', 'Thickness(mm)', 'Weight(g)', 'Volume(ml)', 'Capacity', 'Brand', 'Material', 'Model', 'Halal', 'Origin Country', 'Refer Website', 'Description', 'Virtual', 'Status', 'flag', 'Thumbnail_ID' );

		$default_column['default'] = array( 'name', 'print_name', 'label_name','chinese_name','indo_name', '_sku', 'code', 'serial', 'serial2', 'parent','_uom_code', '_content_uom', '_self_unit', '_parent_unit', 'inconsistent_unit', 'kg_stock', 'grp_id', 'category', '_stock_out_type', '_weight_scale_key', '_sellable', '_length', '_height', '_width', '_thickness', '_weight', '_volume', '_capacity', '_brand', '_material', '_model', '_halal', '_origin_country', '_website', 'desc', 'virtual', 'status', 'flag', '_thumbnail_id');

		$default_column['unique'] = array( 'serial' );

		$default_column['required'] = array( 'name', '_uom_code', 'grp_id' );

		return $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		return $this->Logic->get_export_data( $params );
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;

		$succ = true;
		$columns = $this->im_ex_default_column();
		$unique = $columns['unique'];
		$required = $columns['required'];
		$update_list = [];
		$save_list = [];
		$delete_list = [];
		$restore_list = [];
		$parent_list = [];

		$category_list = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		$category_list = $this->rearrange_array_by_key( $category_list, 'slug' );

		foreach( $datas as $i => $data )
		{
			//validation
			if( !empty( $required ) )
			{
				$hasEmpty = false;
				foreach( $required as $key )
				{
					if( empty( $data[ $key ] ) ) $hasEmpty = true;
				}
				if( $hasEmpty )
				{
					$this->Notices->set_notice( 'Data missing required fields', 'error' );
					$succ = false;
					break;
				}
			}

			//----------------------------------------------------------------- Map Data
			
			if( $data['grp_id'] )
			{
				$key = ( $args['grp_id'] )? $args['grp_id'] : 'code';
				$dat = apply_filters( 'wcwh_get_item_group', [ $key=>$data['grp_id'] ], [], true, [] );
				$data['grp_id'] = ( $dat )? $dat['id'] : '';
			}
			if( $data['store_type_id'] )
			{
				$key = ( $args['store_type_id'] )? $args['store_type_id'] : 'code';
				$dat = apply_filters( 'wcwh_get_store_type', [ $key=>$data['store_type_id'] ], [], true, [] );
				$data['store_type_id'] = ( $dat )? $dat['id'] : '';
			}
			if( $data['category'] )
			{
				$data['category'] = ( $category_list[ $data['category'] ]['term_id'] )? $category_list[ $data['category'] ]['term_id'] : '';
			}
			if( $data['_brand'] )
			{
				$key = ( $args['_brand'] )? $args['_brand'] : 'name';
				$dat = apply_filters( 'wcwh_get_brand', [ $key=>$data['_brand'] ], [], true, [] );
				$data['_brand'] = ( $dat )? $dat['code'] : '';
			}
			if( $data['serial2'] && ! is_array( $data['serial2'] ) )
			{
				$data['serial2'] = json_decode( $data['serial2'], true);
			}
			if( $data['returnable_item'] )
			{
				$rtn_found = apply_filters( 'wcwh_get_item', [ 'code'=>$data['returnable_item'] ], [], true, [] );
				if( $rtn_found ) $data['returnable_item'] = $rtn_found['id'];
				else unset( $data['returnable_item'] );
			}

			//-----------------------------------------------------------------

			$id = 0; $curr = [];
			if( !empty( $unique ) )
			{
				foreach( $unique as $key )
				{
					if( ! empty( $data[ $key ] ) )
					{
						$found = apply_filters( 'wcwh_get_item', [ $key=>$data[ $key ] ], [], true, [] );
						if( $found )
						{
							$id = $found['id'];
							$curr = $found;
							break;
						}
					}
				}
			}
			
			//-- image
			$atth = $data['_thumbnail_id']; $data['_thumbnail_id'] = 0;
			if( ! empty( $atth ) )
			{
				add_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10, 4 );
				$thumbnail_id = $this->upload_file_to_media_by_url( $atth, $id );
				remove_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10 );
				if( $thumbnail_id ) $data['_thumbnail_id'] = $thumbnail_id;
			}
			//-- image
			
			if( $id )	//record found; update
			{
				$data['id'] = $id;
				$update_list[$i] = $data;
			}
			else 		//record not found; add
			{
				$save_list[$i] = $data;
			}
			
			if( $id && (int)$curr['status'] != (int)$data['status'] && (int)$data['status'] <= 0 )
			{
				$delete_list[$i] = $data;
			}
			else if( $id && (int)$curr['status'] != (int)$data['status'] && (int)$data['status'] > 0 )
			{
				$restore_list[$i] = $data;
			}
			
			if( $data['parent'] || $data['ref_prdt'] )
			{
				$parent_list[$i] = $data;
				unset( $data['parent'] ); unset( $data['ref_prdt'] );
			}
		}

		$imp_lists = [ 'save'=>$save_list, 'restore'=>$restore_list, 'delete'=>$delete_list, 'update'=>$update_list ];
		//pd($imp_lists);
		
		if( $succ && $imp_lists )
		{
			wpdb_start_transaction( $this->db_wpdb );
			
			$this->unique_field = array();

			foreach( $imp_lists as $action => $lists )
			{
				if( $succ && $lists )
				{
					foreach( $lists as $i => $line )
					{	
						$outcome = $this->action_handler( $action, $line, [], false );
						if( ! $outcome['succ'] ) 
						{
							$succ = false;
							break;
						}
					}
				}
			}
			
			if( $succ && $parent_list )	//parent / prdt ref handling
			{
				foreach( $parent_list as $i => $data )
				{
					if( ! $data['id'] && ! empty( $unique ) )
					{
						foreach( $unique as $key )
						{
							if( ! empty( $data[ $key ] ) )
							{
								$found = apply_filters( 'wcwh_get_item', [ $key=>$data[ $key ] ], [], true, [] );
								if( $found )
								{
									$data['id'] = $found['id'];
									break;
								}
							}
						}
					}
					
					if( $data['parent'] )
					{
						$key = ( $args['parent'] )? $args['parent'] : 'serial';
						$dat = apply_filters( 'wcwh_get_item', [ $key=>$data['parent'] ], [], true, [] );
						$data['parent'] = ( $dat )? $dat['id'] : '';
					}
					if( $data['ref_prdt'] )
					{
						$key = ( $args['ref_prdt'] )? $args['ref_prdt'] : 'serial';
						$dat = apply_filters( 'wcwh_get_item', [ $key=>$data['ref_prdt'] ], [], true, [] );
						$data['ref_prdt'] = ( $dat )? $dat['id'] : '';
					}
					
					if( $data['parent'] || $data['ref_prdt'] )
					{
						$outcome = $this->action_handler( 'update', $data, [], false );
						if( ! $outcome['succ'] ) 
						{
							$succ = false;
							break;
						}
					}
				}
			}

			wpdb_end_transaction( $succ, $this->db_wpdb );
		}

		if( ! $succ )
			$this->Notices->set_notice( 'Import Failed', 'error' );

		return $succ;
	}

		public function upload_file_to_media_by_url( $image_url, $t_id )
		{				
			// allows us to use download_url() and wp_handle_sideload() functions
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		
			// download to temp dir
			$temp_file = wcwh_download_url( $image_url );
			
			if( is_wp_error( $temp_file ) ) {
				return false;
			}
		
			// move the temp file into the uploads directory
			$file = array(
				'name'     => basename( $image_url ),
				'type'     => mime_content_type( $temp_file ),
				'tmp_name' => $temp_file,
				'size'     => filesize( $temp_file ),
			);
			$sideload = wp_handle_sideload(
				$file,
				array(
					'test_form'   => false // no needs to check 'action' parameter
				)
			);
			
			if( ! empty( $sideload[ 'error' ] ) ) {
				// you may return error message if you want
				return false;
			}
			
			$item_info = apply_filters( 'wcwh_get_item', ['id'=>$t_id], [], true, [ 'uom'=>1 ] );
			if($item_info['_thumbnail_id'] > 0 )
			{
				$post = get_post($item_info['_thumbnail_id']);
				$post_meta = get_post_meta($post->ID, '_source_url', true);
				
				if($post_meta == $image_url)
				{
					$attachment_id = $post->ID;
				}
				else
				{
					// add our uploaded image into WordPress media library
					$attachment_id = wp_insert_attachment(
						array(
							'guid'           => $sideload[ 'url' ],
							'post_mime_type' => $sideload[ 'type' ],
							'post_title'     => basename( $sideload[ 'file' ] ),
							'post_content'   => '',
							'post_status'    => 'inherit',
						),
						$sideload[ 'file' ]
					);

					update_post_meta($attachment_id,'_source_url',$image_url);
				}
			}
			else
			{
				$attachment_id = wp_insert_attachment(
						array(
							'guid'           => $sideload[ 'url' ],
							'post_mime_type' => $sideload[ 'type' ],
							'post_title'     => basename( $sideload[ 'file' ] ),
							'post_content'   => '',
							'post_status'    => 'inherit',
						),
						$sideload[ 'file' ]
				);
				
				update_post_meta($attachment_id,'_source_url',$image_url);
			}

			if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
				return false;
			}

			// update medatata, regenerate image sizes
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			wp_update_attachment_metadata( $attachment_id,	wp_generate_attachment_metadata( $attachment_id, $sideload[ 'file' ] ) );
			
			return $attachment_id;	
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Item" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Item"
				>
					<?php echo $actions['save'] ?> Item
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> Items" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> Items"
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
					data-title="<?php echo $actions['export'] ?> Items" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Items"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
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
			'rowTpl'	=> $this->tplName['row'],
			'wh_code'	=> $this->warehouse['code'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];
		
		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = $this->Logic->get_infos( $filters, [], true, [ 'parent'=>true, 'uom'=>true, 'category'=>true, 'reorder_type'=>$this->warehouse['code'] ] );
			if( $datas )
			{	
				//metas
				$metas = get_items_meta( $id );

				if( is_json( $datas['serial2'] ) ) $datas['serial2'] = json_decode( stripslashes( $datas['serial2'] ), true );
				if( $datas['serial2'] && ! is_array( $datas['serial2'] ) ) $datas['serial2'] = [ $datas['serial2'] ];

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;
				
				$args['data'] = $datas;
				if( $metas )
				{
					foreach( $metas as $key => $value )
					{
						$args['data'][$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
					}
				}
				unset( $args['new'] );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/item-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/item-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/item_sku-row.php', $this->tplName['row'] );
	}

	public function print_tpl()
	{
		$tpl_code = "itemlabel0001";
		$tpl = apply_filters( 'wcwh_get_suitable_template', $tpl_code );
		if( $tpl )
		{
			do_action( 'wcwh_templating', $tpl['tpl_path'].$tpl['tpl_file'], 'product_label', $args );
		}
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

		do_action( 'wcwh_templating', 'import/import-item.php', $this->tplName['import'], $args );
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

		do_action( 'wcwh_templating', 'export/export-item.php', $this->tplName['export'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/itemListing.php" ); 
			$Inst = new WCWH_Item_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->styles = [
				'#uom_code' => [ 'width' => '50px' ],
				'#inconsistent' => [ 'width' => '50px' ],
				'#scale_key' => [ 'width' => '50px' ],
				'#_sellable' => [ 'width' => '56px' ],
				'#status' => [ 'width' => '70px' ],
			];
			$Inst->warehouse = $this->warehouse;
			
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

			$datas = $this->Logic->get_infos( $filters, $order, false, 
				[ 'parent'=>1, 'uom'=>1, 'group'=>1, 'store'=>1, 'category'=>1, 'brand'=>1, 'reorder_type'=>$this->warehouse['code'],
					'meta'=>[ '_weight_scale_key', 'inconsistent_unit', '_sellable' ], 
					'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] , [], $limit
			);
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}