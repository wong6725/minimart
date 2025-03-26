<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_StockTake_Class" ) ) include_once( WCWH_DIR . "/includes/classes/stocktake.php" ); 
if ( !class_exists( "WCWH_Item_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item.php" ); 

if ( !class_exists( "WCWH_StockTake_Controller" ) ) 
{

class WCWH_StockTake_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_stocktake";

	public $Notices;
	public $className = "StockTake_Controller";

	public $Logic;
	public $Item;

	public $tplName = array(
		'new' => 'newStockTake',
		'row' => 'rowStockTake',
		'import' => 'importST',
		'export' => 'exportST',
		'print' => 'printST',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;

	public $processing_stat = [ 1, 3 ];

	public $stocktake_item = [ 'apply_all', 'store_type' ];

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
		$this->Logic = new WCWH_StockTake_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->processing_stat = $this->processing_stat;

		$this->Item = new WCWH_Item_Class();
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
					if( ! $datas['detail'] && ! in_array( $datas['header']['stocktake_item'], $this->stocktake_item ) )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					if( $datas['header']['stocktake_item'] == 'store_type' && ! $datas['header']['store_type_id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
				break;
				case 'delete':
				case 'post':
				case 'unpost':
				case 'approve':
				case 'reject':
				case "complete":
				case "incomplete":
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

	public function action_handler( $action, $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
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

					if( $header['doc_id'] )
						$exist = $this->Logic->get_header( [ 'doc_id'=>$header['doc_id'], 'doc_type'=>'none' ], [], true, [ 'meta'=>['stocktake_item'] ] );

					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $exist['doc_date'];
					$header['doc_date'] = !empty( $header['doc_date'] )? $header['doc_date'] : $now;

					$header['doc_type'] = 'stocktake';
					$strg_id = apply_filters( 'wcwh_get_system_storage', 0, $header );

					$inventory = [];
					if( $exist )
					{
						$filter = [
							'warehouse_id' => $exist['warehouse_id'],
							'strg_id' => $strg_id,
							'doc_date' => $header['doc_date'],
						];
						
						$stocks = $this->Logic->get_stocktake_stocks( $filter );
						if( $stocks )
						{
							foreach( $stocks as $i => $stock )
							{
								$inventory[ $stock['product_id'] ] = $stock;
							}
						}
					}
					
					if( $header['stocktake_item'] != 'store_type' ) $header['store_type_id'] = 0;
					
					if( $detail )
					{
						$childs = []; $prdt_idx = [];
						foreach( $detail as $i => $item )
						{
							$prdt_idx[ $item['product_id'] ] = $i;

							if( ! $item['bqty'] )
							{
								$detail[$i]['bqty'] = 0;
							}
							$detail[$i]['strg_id'] = $strg_id;

							if( $exist && $inventory[ $item['product_id'] ] )
							{
								$inv = $inventory[ $item['product_id'] ];

								$detail[$i]['stock_bal_qty'] = $inv['stock_bal_qty'];
								$detail[$i]['stock_bal_unit'] = $inv['stock_bal_unit'];

								$detail[$i]['adjust_qty'] = $item['bqty'] - $inv['stock_bal_qty'];
								$detail[$i]['adjust_unit'] = ( $detail[$i]['adjust_qty'] != 0 )? $item['bunit'] - $inv['stock_bal_unit'] : 0;
								$detail[$i]['plus_sign'] = ( $detail[$i]['adjust_qty'] < 0 )? '-' : '+';
								if( $detail[$i]['adjust_qty'] > 0 )
								{
									$detail[$i]['total_amount'] = $inv['total_price'] / $inv['stock_in_qty'] * $detail[$i]['adjust_qty'];

									if( $inv['stock_bal_qty'] > 0 )
										$detail[$i]['total_amount'] = $inv['balance'] / $inv['stock_bal_qty'] * $detail[$i]['adjust_qty'];
								}
								else
									$detail[$i]['total_amount'] = 0;

								$detail[$i]['adjust_qty'] = abs( $detail[$i]['adjust_qty'] );

								if( $inv['parent'] > 0 )
								{
									$base_id = 0;
									$base_qty = $this->Item->uom_conversion( $item['product_id'], $item['bqty'], $base_id );
									$item['base_qty'] = ( ! is_array( $base_qty ) && $base_qty > 0 )? $base_qty : 0;
									$item['base_id'] = $base_id;
									$item['idx'] = $i;
									$childs[ $item['product_id'] ] = $item;
								}
							}
						}
						
						if( $childs )
						{
							foreach( $childs as $i => $child )
							{
								//child item
								$idx = $child['idx'];
								$detail[ $idx ]['adjust_qty'] = 0;
								$detail[ $idx ]['adjust_unit'] = 0;
								$detail[ $idx ]['plus_sign'] = ( $detail[ $idx ]['adjust_qty'] < 0 )? '-' : '+';
								$detail[ $idx ]['total_amount'] = 0;

								//base item
								$idx = $prdt_idx[ $child['base_id'] ];
								if( $inventory[ $child['base_id'] ] )
								{
									$inv = $inventory[ $child['base_id'] ];
									$bqty = $detail[$idx]['bqty'] + $child['base_qty'];
									$bunit = $detail[$idx]['bunit'] + $child['bunit'];
									$detail[$idx]['adjust_qty'] = $bqty - $inv['stock_bal_qty'];
									$detail[$idx]['adjust_unit'] = ( $detail[$idx]['adjust_qty'] != 0 )? $bunit - $inv['stock_bal_unit'] : 0;
									$detail[$idx]['plus_sign'] = ( $detail[$idx]['adjust_qty'] < 0 )? '-' : '+';
									if( $detail[$idx]['adjust_qty'] > 0 )
									{
										$detail[$idx]['total_amount'] = $inv['total_price'] / $inv['stock_in_qty'] * $detail[$idx]['adjust_qty'];

										if( $inv['stock_bal_qty'] > 0 )
											$detail[$idx]['total_amount'] = $inv['balance'] / $inv['stock_bal_qty'] * $detail[$idx]['adjust_qty'];
									}
									else
										$detail[$idx]['total_amount'] = 0;
									$detail[$idx]['adjust_qty'] = abs( $detail[$idx]['adjust_qty'] );
								}
							}
						}
					}
					//pd($detail);exit;
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
				case "confirm":
				case "unconfirm":
				case "post":
				case "unpost":
				case "complete":
				case "incomplete":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
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
					switch( strtolower( $datas['type'] ) )
					{
						case 'variance':
							$datas['filename'] = 'Stocktake Variance';
						break;
						case 'stocktake':
						default:
							$datas['filename'] = 'Stocktake List';
						break;
					}

					$params = [];
					$params['status'] = !empty( $datas['status'] )? $datas['status'] : 1;
					if( !empty( $datas['store_type'] ) && $datas['stocktake_item'] == 'store_type' ) $params['store_type'] = $datas['store_type'];
					if( !empty( $datas['type'] ) ) $params['type'] = $datas['type'];

					$datas['doc_id'] = !empty( $datas['doc_id'] )? $datas['doc_id'] : $datas['id'];
					if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];

					if( $datas['doc_id'] )
					{
						$doc = $this->Logic->get_header( [ 'doc_id' => $datas['doc_id'] ], [], true, ['meta'=>[ 'stocktake_item', 'store_type_id' ] ] );
						if( $doc )$datas['filename'].= ' '.$doc['docno'].' ';
						
						if( $doc && ! in_array( $doc['stocktake_item'], $this->stocktake_item ) )
						{
							$prdt = [];
							$list = $this->Logic->get_detail( [ 'doc_id' => $datas['doc_id'] ], [], false, [ 'usage'=>1 ] );
							if( $list )
							{
								foreach( $list as $i => $row )
								{
									$prdt[] = $row['product_id'];
								}

								$params['product_id'] = $prdt;
							}
						}
						else if( $doc && in_array( $doc['stocktake_item'], [ 'store_type' ] ) )
						{
							$params['store_type'] = $doc['store_type_id'];
						}
					}

					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					switch( strtolower( $datas['type'] ) )
					{
						case 'stocktake':
							$params = [];
							$params['status'] = !empty( $datas['status'] )? $datas['status'] : 1;
							if( !empty( $datas['store_type'] ) && $datas['stocktake_item'] == 'store_type' ) $params['store_type'] = $datas['store_type'];
							$params['type'] = $datas['type'];
							
							$this->print_handler( $params, $datas );
						break;
						case 'variance':
							$params = [];
							if( !empty( $datas['status'] ) ) $params['status'] = $datas['status'];
							if( !empty( $datas['store_type'] ) ) $params['store_type'] = $datas['store_type'];

							$datas['doc_id'] = !empty( $datas['doc_id'] )? $datas['doc_id'] : $datas['id'];
							if( !empty( $datas['doc_id'] ) ) $params['doc_id'] = $datas['doc_id'];
							$params['type'] = $datas['type'];
							
							$this->print_handler( $params, $datas );
						break;
						default:
							$this->print_form( $datas['id'] );
						break;
					}
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	//if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
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

			$exists = $this->Logic->get_header( [ 'doc_id' => $id ], [], false );
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
			}
		}

		return $succ;
	}


	public function print_handler( $params = array(), $opts = array() )
	{
		$opts['doc_id'] = !empty( $opts['doc_id'] )? $opts['doc_id'] : $opts['id'];
		if( $opts['doc_id'] )
		{
			$doc = $this->Logic->get_header( [ 'doc_id' => $opts['doc_id'] ], [], true, ['meta'=>[ 'stocktake_item', 'store_type_id' ] ] );
			if( $doc && ! in_array( $doc['stocktake_item'], $this->stocktake_item ) )
			{
				$prdt = [];
				$list = $this->Logic->get_detail( [ 'doc_id' => $opts['doc_id'] ], [], false, ['usage'=>1] );
				if( $list )
				{
					foreach( $list as $i => $row )
					{
						$prdt[] = $row['product_id'];
					}

					$params['product_id'] = $prdt;
				}
			}
			else if( $doc && in_array( $doc['stocktake_item'], [ 'store_type' ] ) )
			{
				$params['store_type'] = $doc['store_type_id'];
			}
		}

		$datas = $this->export_data_handler( $params );
		$date_format = get_option( 'date_format' );

		$type = strtolower( $params['type'] );
		switch( $type )
		{
			case 'variance':
				$opts['orientation'] = 'landscape';
				$filename = "Variance ".$doc['docno'];
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = [ 'font_size'=>8 ];
				$document['header'] = 'Stocktake Variance';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Stocktake Variance';
						
				$document['heading']['title'].= " On ".date_i18n( $date_format, strtotime( $doc['doc_date'] ) );
						
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
						
				$document['detail_title'] = [
					'No.' => [ 'width'=>'3%', 'class'=>['leftered'] ],
					'Base Code' => [ 'width'=>'5%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'17%', 'class'=>['leftered'] ],
					'Item' => [ 'width'=>'25%', 'class'=>['leftered'] ],
					'UOM' => [ 'width'=>'5%', 'class'=>['leftered'] ],
					//'Converse' => [ 'width'=>'6%', 'class'=>[] ],
					'Stock Qty' => [ 'width'=>'5%', 'class'=>[''] ],
					'Stock Metric' => [ 'width'=>'5%', 'class'=>[] ],
					'Count Qty' => [ 'width'=>'5%', 'class'=>[] ],
					'Converse Qty' => [ 'width'=>'5%', 'class'=>[] ],
					'Count Metric' => [ 'width'=>'5%', 'class'=>[] ],
					'Adjust Type' => [ 'width'=>'5%', 'class'=>[] ],
					'Variance Qty' => [ 'width'=>'5%', 'class'=>[] ],
					'Variance Metric' => [ 'width'=>'5%', 'class'=>[] ],
					'Total Price' => [ 'width'=>'5%', 'class'=>[] ],
				];

				if( $datas )
				{
					$regrouped = [];
					$rowspan = [];
					$totals = [];
					foreach( $datas as $i => $data )
					{
						$product = [];
						if( $data['item_code'] ) $product[] = $data['item_code'];
						if( $data['item_name'] ) $product[] = $data['item_name'];
						$data['product'] = implode( ' - ', $product );

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];
						$data['category'] = implode( ' - ', $category );

						if( $data['adjust_type'] == "+" ) $data['adjust_type'] = "In +";
						else if( $data['adjust_type'] == "-" ) $data['adjust_type'] = "OUT -";
						else $data['adjust_type'] = "";

						$regrouped[ $data['base_code'] ][$i] = $data;

						//rowspan handling
						if( count( $regrouped[ $data['base_code'] ] ) > 1 )
							$rowspan[ $data['base_code'] ]+= 1;

						//totals
							$totals[ $data['base_code'] ]['stock_qty']+= $data['stock_bal_qty'];
							$totals[ $data['base_code'] ]['stock_unit']+= $data['stock_bal_unit'];
							$totals[ $data['base_code'] ]['adjust_type'] = $data['adjust_type'];
							$totals[ $data['base_code'] ]['variance_qty']+= $data['variance_qty'];
							$totals[ $data['base_code'] ]['variance_unit']+= $data['variance_unit'];
							$totals[ $data['base_code'] ]['total_price']+= $data['total_price'];
						
						$totals[ $data['base_code'] ]['converted_qty']+= $data['converted_qty'];
						$totals[ $data['base_code'] ]['count_unit']+= $data['count_unit'];
					}
					//pd($totals);exit;
					$details = [];
					if( $regrouped )
					{
						$n = 1;
						foreach( $regrouped as $key => $items )
						{
							$doc_added = '';

							foreach( $items as $i => $vals )
							{
								$row = [

'no' => [ 'value'=>$n, 'class'=>['leftered'], 'rowspan'=>$rowspan[ $key ] ],
'base_code' => [ 'value'=>$vals['base_code'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $key ] ],
'category' => [ 'value'=>$vals['category'], 'class'=>['leftered'], 'rowspan'=>$rowspan[ $key ] ],
'item' => [ 'value'=>$vals['product'], 'class'=>['leftered'] ],
'uom' => [ 'value'=>$vals['uom'], 'class'=>['leftered'] ],
'stock_qty' => [ 'value'=>$vals['stock_bal_qty'], 'class'=>['rightered'], 'num'=>1 ],
'stock_unit' => [ 'value'=>$vals['stock_bal_unit'], 'class'=>['rightered'], 'num'=>1 ],
'count_qty' => [ 'value'=>$vals['count_qty'], 'class'=>['rightered'], 'num'=>1 ],
'converted_qty' => [ 'value'=>$vals['converted_qty'], 'class'=>['rightered'], 'num'=>1 ],
'count_unit' => [ 'value'=>$vals['count_unit'], 'class'=>['rightered'], 'num'=>1 ],
'adjust_type' => [ 'value'=>( $rowspan[ $key ]? '' : $vals['adjust_type'] ), 'class'=>['centered'] ],
'variance_qty' => [ 'value'=>( $rowspan[ $key ]? 0 : $vals['variance_qty'] ), 'class'=>['rightered'], 'num'=>1 ],
'variance_unit' => [ 'value'=>( $rowspan[ $key ]? 0 : $vals['variance_unit'] ), 'class'=>['rightered'], 'num'=>1 ],
'total_price' => [ 'value'=>( $rowspan[ $key ]? 0 : $vals['total_price'] ), 'class'=>['rightered'], 'num'=>1 ],

								];

								if( $doc_added == $key ) 
								{
									$row['no'] = [];
									$row['base_code'] = [];
									$row['category'] = [];
								}
								else
									$n++;
								$doc_added = $key;

								$details[] = $row;
							}

							if( $rowspan[ $key ] )
							{
								$details[] = [
									'no' => [],
									'base_code' => [],
									'category' => [],
									'item' => [ 'value'=>$key.' Total:', 'class'=>['leftered','bold'], 'colspan'=>2 ],
									'stock_qty' => [ 'value'=>$totals[ $key ]['stock_qty'], 'class'=>['rightered','bold'], 'num'=>1 ],
									'stock_unit' => [ 'value'=>$totals[ $key ]['stock_unit'], 'class'=>['rightered','bold'], 'num'=>1 ],
									'count_qty' => [],
									'converted_qty' => [ 'value'=>$totals[ $key ]['converted_qty'], 'class'=>['rightered','bold'], 'num'=>1 ],
									'count_unit' => [ 'value'=>$totals[ $key ]['count_unit'], 'class'=>['rightered','bold'], 'num'=>1 ],
									'adjust_type' => [ 'value'=>$totals[ $key ]['adjust_type'], 'class'=>['centered','bold'] ],
									'variance_qty' => [ 'value'=>$totals[ $key ]['variance_qty'], 'class'=>['rightered','bold'], 'num'=>1 ],
									'variance_unit' => [ 'value'=>$totals[ $key ]['variance_unit'], 'class'=>['rightered','bold'], 'num'=>1 ],
									'total_price' => [ 'value'=>$totals[ $key ]['total_price'], 'class'=>['rightered','bold'], 'num'=>1 ],
								];
							}
						}
					}

					$document['detail'] = $details;
				}
			break;
			case 'stocktake':
			default:
				$filename = "Stocktake ".$doc['docno'];
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = [ 'font_size'=>8 ];
				$document['header'] = 'Stocktake List';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Stocktake List';
						
				$document['heading']['title'].= " On ".date_i18n( $date_format, strtotime( $doc['doc_date'] ) );
						
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
						
				$document['detail_title'] = [
					'No.' => [ 'width'=>'4%', 'class'=>['leftered'] ],
					'Item' => [ 'width'=>'40%', 'class'=>['leftered'] ],
					//'Group' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					//'Brand' => [ 'width'=>'8%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'24%', 'class'=>['leftered'] ],
					'UOM' => [ 'width'=>'4%', 'class'=>['leftered'] ],
					'Store Type' => [ 'width'=>'9%', 'class'=>['leftered'] ],
					'Base Item' => [ 'width'=>'7%', 'class'=>['leftered'] ],
					'Qty' => [ 'width'=>'6%', 'class'=>[] ],
					'Metric (kg/l)' => [ 'width'=>'6%', 'class'=>[] ],
				];

				if( $datas )
				{
					$details = [];
					$n = 0;
					foreach( $datas as $i => $data )
					{
						$product = [];
						if( $data['code'] ) $product[] = $data['code'];
						if( $data['name'] ) $product[] = $data['name'];
						$data['item'] = implode( ' - ', $product );

						$n++;
						$row = [

		'no' => [ 'value'=>$n, 'class'=>['leftered'] ],
		'item' => [ 'value'=>$data['item'], 'class'=>['leftered'] ],
		//'group' => [ 'value'=>$data['item_group'], 'class'=>['leftered'] ],
		//'brand' => [ 'value'=>$data['brand'], 'class'=>['leftered'] ],
		'category' => [ 'value'=>$data['category'], 'class'=>['leftered'] ],
		'uom' => [ 'value'=>$data['uom'], 'class'=>['leftered'] ],
		'store_type' => [ 'value'=>$data['store_type'], 'class'=>['leftered'] ],
		'base_code' => [ 'value'=>$data['base_code'], 'class'=>['leftered'] ],
		'qty' => [ 'value'=>$data['bqty'], 'class'=>['centered'] ],
		'unit' => [ 'value'=>$data['bunit'], 'class'=>['centered'] ],

						];

						$details[] = $row;
					}

					$document['detail'] = $details;		
				}
			break;
		}

		//pd($document);
		ob_start();
							
		do_action( 'wcwh_get_template', 'template/doc-summary-general.php', $document );
				
		$content.= ob_get_clean();
		//echo $content;exit;
		if( is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) ){
			$paper = [ 'size' => 'A4', 'orientation' => $opts['orientation']? $opts['orientation'] : 'portrait' ];
			$args = [ 'filename' => $filename ];
			do_action( 'dompdf_generator', $content, $paper, array(), $args );
		}
		else{
			echo $content;
		}

		exit;
	}


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = [];
		$type = $params['type'];

		switch( strtolower($type) )
		{
			case 'variance':
				$default_column['title'] = [ 'Item ID', 'Base Code', 'Item Code', 'Item Name', 'Category Code', 'Category Name', 'Inconsistent Metric (kg/l)', 'UOM', 'Breadcruob', 'Base Conversion', 'Stock Qty', 'Stock Metric (kg/l)', 'Count Qty', 'Converted Qty', 'Count Metric (kg/l)', 'Adjust Type', 'Variance Qty', 'Variance Metric (kg/l)', 'Total Price' ];
			break;
			case 'stocktake':
			default:
				$default_column['title'] = [ 'Item Code', 'Item Name', 'Item Group', 'Brand', 'Category', 'Inconsistent Metric (kg/l)', 'UOM', 'Store Type', 'Base Item Code', 'Base Conversion', 'Qty', 'Metric (kg/l)' ];

				$default_column['default'] = [ 
					'code', 'name', 'item_group', 'brand', 'category', 'inconsistent_unit', 'uom', 'store_type', 'base_code', 'base_conversion', 'bqty', 'bunit'
				];

				$default_column['unique'] = [ 'code' ];

				$default_column['required'] = [];
			break;
		}

		return $default_column;
	}

	public function export_data_handler( $params = array() )
	{
		$type = $params['type'];
		unset( $params['type'] );

		switch( strtolower($type) )
		{
			case 'variance':
				return $this->Logic->get_variance_list( $params );
			break;
			case 'stocktake':
			default:
				return $this->Logic->get_stocktake_list( $params );
			break;
		}
	}

	public function import_data_handler( $datas, $args = array() )
	{
		if( ! $datas ) return false;
		$succ = true;
		$columns = $this->im_ex_default_column( $args );
		$unique = $columns['unique'];
		$required = $columns['required'];
		$unchange = $columns['unchange'];

		$header = $args['header'];
		$detail = $args['detail'];

		$doc = [];
		$doc_list = [];
		if( $header['doc_id'] > 0 )
		{
			$doc = $this->Logic->get_header( [ 'doc_id' => $header['doc_id'] ], [], true, ['meta'=>[ 'stocktake_item', 'store_type_id' ] ] );
			if( $doc )
			{
				$list = $this->Logic->get_detail( [ 'doc_id' => $header['doc_id'] ], [], false, ['usage'=>1] );
				if( $list )
				{
					foreach( $list as $row )
					{
						$doc_list[ $row['product_id'] ] = $row;
					}
				}
			}
		}

		$detail = [];
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
			
			//filter empty
			if( $data['bqty'] === '' || $data['bqty'] === null || strlen( $data['bqty'] ) <= 0 ) continue;

			//----------------------------------------------------------------- Map Data
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
			
			if( in_array( $doc['stocktake_item'], $this->stocktake_item ) )
			{
				$row = [
					'product_id' => $id,
					'item_id' => '',
					'bqty' => ( $data['bqty'] > 0 )? $data['bqty'] : 0,
					'bunit' => ( is_numeric( $data['bunit'] ) && $data['bunit'] > 0 )? $data['bunit'] : 0,
				];

				if( ! empty( $doc_list[ $id ] ) ) $row['item_id'] = $doc_list[ $id ]['item_id'];
			}
			else
			{
				if( ! empty( $doc_list[ $id ] ) )
				{
					$row = [
						'product_id' => $id,
						'item_id' => $doc_list[ $id ]['item_id'],
						'bqty' => ( $data['bqty'] > 0 )? $data['bqty'] : 0,
						'bunit' => ( is_numeric( $data['bunit'] ) && $data['bunit'] > 0 )? $data['bunit'] : 0,
					];
				}
			}

			$detail[] = $row;
		}
		
		if( $succ && ! empty( $detail ) )
		{
			wpdb_start_transaction( $this->db_wpdb );

			$form = [];
			$form['header'] = $header;
			$form['detail'] = $detail;

			$outcome = $this->action_handler( 'update', $form, [] );
			if( ! $outcome['succ'] ) 
			{
				$succ = false;
			}

			wpdb_end_transaction( $succ, $this->db_wpdb );
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> StockTake" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> StockTake"
				>
					<?php echo $actions['save'] ?> StockTake
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> Stocktake Document" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> Stocktake Document"
				>
					<i class="fa fa-upload" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'export':
				if( current_user_cans( [ 'export_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Stocktake Items" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Stocktake Items"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'print':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print'] ?>" 
					data-title="<?php echo $actions['print'] ?> Stocktake List" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?> Stocktake List"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function gen_form( $ids = array() )
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
		);

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $ids )
		{
			$items = apply_filters( 'wcwh_get_item', [ 'id'=>$ids ], [], false, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
			if( $items )
			{
				$details = array();
				foreach( $items as $i => $item )
				{	
					$details[$i] = array(
						'id' =>  $item['id'],
						'bqty' => '',
						'bunit' => '',
						'product_id' => $item['id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['name'], 'code'=>$item['code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
						],
						'plus_sign' => '',
					);
				}
				$args['data']['details'] = $details;
			}
		}

		do_action( 'wcwh_get_template', 'form/stocktake-form.php', $args );
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
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [ 'meta'=>['stocktake_item'] ] );
			if( $datas )
			{	
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
				//metas
				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1, 'usage'=>1 ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id' => $id ], [], false, [ 'uom'=>1 ] );
				//pd( $datas['details'] );
				$args['action'] = 'update';
				if( $datas['status'] == 3 ) $args['edit'] = $id;
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();
		        	
		        if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'required_unit'=>$item['required_unit']
		        		];

		        		if( $item['item_id'] )
		        		{
		        			$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        			$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );
		        		}

		        		if( $isView )
		        		{
		        			switch( $datas['details'][$i]['plus_sign'] )
			        		{
			        			case "+":
			        				$datas['details'][$i]['plus_sign'] = "In +";
			        			break;
			        			case "-":
			        				$datas['details'][$i]['plus_sign'] = "Out -";
			        			break;
			        		}
		        		}
		        		
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );
				
		        $args['render'] = $Inst->get_listing( [
		        		'num' => '',
		        		'prdt_name' => 'Item',
		        		'uom_code' => 'UOM',
		        		'stock_bal_qty' => 'Stock Qty',
		        		'stock_bal_unit' => 'Stock Metric (kg/l)',
		        		'bqty' => 'Count Qty',
		        		'bunit' => 'Count Metric (kg/l)',
		        		'plus_sign' => 'Adjust Type',
		        		'adjust_qty' => 'Adjust Qty',
		        		'adjust_unit' => 'Adjust Metric (kg/l)',
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
			do_action( 'wcwh_templating', 'form/stocktake-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/stocktake-form.php', $args );
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/stocktake-row.php', $this->tplName['row'] );
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

		do_action( 'wcwh_templating', 'import/import-stocktake.php', $this->tplName['import'], $args );
	}

	public function view_import_form( $id = 0 )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'import',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['import'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id ], [], true, [ 'meta'=>[ 'stocktake_item', 'store_type_id' ] ] );
			if( $datas ) $args['doc_id'] = $datas['doc_id'];

			$args['data'] = $datas;

			do_action( 'wcwh_get_template', 'import/import-stocktake.php', $args );
		}
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

		do_action( 'wcwh_templating', 'export/export-stocktake.php', $this->tplName['export'], $args );
	}

	public function printing_form()
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['print'],
			'isPrint'	=> true,
		);

		do_action( 'wcwh_templating', 'export/export-stocktake.php', $this->tplName['print'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
			include_once( WCWH_DIR . "/includes/listing/stocktakeListing.php" ); 
			$Inst = new WCWH_StockTake_Listing();
			$Inst->set_warehouse( $this->warehouse );
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$count = $this->Logic->count_statuses( $this->warehouse['code'], $this->ref_doc_type );
			if( $count ) $Inst->viewStats = $count;

			$filters['status'] = ( isset( $filters['status'] ) && $filters['status'] != '' )? $filters['status'] : 'process';
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			if( empty( $filters['warehouse_id'] ) )
			{
				$filters['warehouse_id'] = $this->warehouse['code'];
			}

			$metas = [ 'remark' ];
			$dmeta = [ 'plus_sign' ];

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_header( $filters, $order, false, [ 'meta'=>$metas, 'dmeta'=>$dmeta ], [], $limit );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}