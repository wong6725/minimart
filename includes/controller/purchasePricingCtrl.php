<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if( ! class_exists( 'WCWH_PurchasePricing' ) ) include_once( WCWH_DIR . "/includes/classes/purchase-pricing.php" ); 
if( ! class_exists( 'WCWH_PriceRef' ) ) include_once( WCWH_DIR . "/includes/classes/price-ref.php" ); 
if( ! class_exists( 'WCWH_Price' ) ) include_once( WCWH_DIR . "/includes/classes/price.php" ); 

if ( !class_exists( "WCWH_PurchasePricing_Controller" ) ) 
{

class WCWH_PurchasePricing_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_purchase_pricing";

	protected $primary_key = "id";

	public $Notices;
	public $className = "PurchasePricing_Controller";

	public $Logic;
	public $Ref;
	public $Detail;

	public $tplName = array(
		'new' => 'newPrice',
		'row' => 'row',
	);

	public $useFlag = false;

	private $temp_data = array();

	protected $import_data = array();

	protected $price_type = 'purchase_price';

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->arrangement_init();

		$this->set_logic();

		//add_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10, 2 );
	}

	public function __destruct() 
	{
        //remove_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10 );
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
		$this->Logic = new WCWH_PurchasePricing( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
		$this->Logic->price_type = $this->price_type;

		$this->Ref = new WCWH_PriceRef( $this->db_wpdb );
		$this->Ref->set_section_id( $this->section_id );

		$this->Detail = new WCWH_Price( $this->db_wpdb );
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
			'name' => '',
			'code' => '',
			'type' => $this->price_type,
			'remarks' => '',
			'status' => 1,
			'flag' => ( $this->useFlag )? 0 : 1,
			'since' => '',
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}
		protected function get_defaultRefFields()
		{
			return array(
				'price_id' => 0,
				'seller' => '',
				'scheme' => 'default',
				'scheme_lvl' => 0,
				'ref_id' => '',
				'status' => 1,
			);
		}
		protected function get_defaultDetailFields()
		{
			return array(
				'price_id' => 0,
				'product_id' => 0,
				'unit_price' => 0,
				'ref_id' => 0,
				'factor' => 0,
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
					if( ! $datas['detail'] && ! $datas['header']['apply_all'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					/*else if( $datas['detail'] && sizeof( $datas['detail'] ) > 200 )
					{
						$succ = false;
						$this->Notices->set_notice( 'Exceeding Maximum item rows of 200.', 'warning' );
					}*/
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

    /*public function docno_replacer( $sdocno, $doc_type = '' )
	{
		if( $doc_type && $doc_type == $this->section_id )
		{	
			$datas = $this->temp_data;
			$ref = array();
			
			if( $datas['seller'] )
			{
				$ref = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['seller'] ], [], true, [ 'usage'=>1 ] );
			}
			
			$find = [ 
				'code' => '{Code}',
			];

			$replace = [ 
				'code' => ( $ref['code'] )? $ref['code'] : '',
			];

			$sdocno = str_replace( $find, $replace, $sdocno );
		}

		return $sdocno;
	}*/

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
        	$price_id = 0;
        	$result = array();
        	$child_result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );

			$schemes = get_schemes( 'pricing' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "save":
					$header = $datas['header'];
				case "update":
					$header = $this->data_sanitizing( $header );
					
					$header = ( $header )? $header : $datas['header'];
					$detail = $datas['detail'];

					$header['lupdate_by'] = $user_id;
					$header['lupdate_at'] = $now;
					
					$ref = [ 'seller' => $header['seller'] ];
					foreach( $schemes as $scheme )
					{
						if( $header['scheme'] == $scheme['scheme'] )
						{
							$ref['scheme'] = $header['scheme'];
							$ref['ref_id'] = ( $header[ $scheme['scheme'] ] )? $header[ $scheme['scheme'] ] : [];
							$ref['scheme_lvl'] = $scheme['scheme_lvl'];
						}
					}
					unset( $header['seller'] );
					unset( $header['scheme'] );
					unset( $header[ $scheme['scheme'] ] );

					$extracted = $this->extract_data( $header );
					$header = $extracted['datas'];
					$metas = $extracted['metas'];
					
					$source = [];
					if( ! $header[ $this->get_primaryKey() ] && $action == 'save' )
					{
						//$this->temp_data = $header;
							
							$sdocno = "Price".get_current_time('YmdHis');
        					$header['sdocno'] =	empty( $header['sdocno'] )? apply_filters( 'warehouse_generate_docno', $sdocno, $this->section_id ) : $header['sdocno'];
							$header['docno'] = empty( $header['docno'] ) ? $header['sdocno'] : $header['docno'];

							$header['code'] = empty( $header['code'] )? apply_filters( 'warehouse_generate_docno', $sdocno, 'price_code' ) : $header['code'];

						//$this->temp_data = array();

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
							$price_id = $result['id'];
						}

						//ref
						$succ = $this->ref_handler( $price_id, $action, $ref );

						//source handling
						if( $succ && $metas['price_source'] )
						{
							if( $metas['apply_all'] ) $detail = [];
							$source = $this->get_source_price( $metas['price_source'], $detail );

							if( ! $detail && $source )
							{
								foreach( $source as $product_id => $row )
								{
									$detail[] = [
										'product_id' => $product_id,
										'item_id' => '',
										'unit_price' => $row['unit_price'],
									];
								}
							}
						}

						if( $succ && $detail )
						{
							foreach( $detail as $i => $row )
							{
								unset( $row['item_id'] );
								$row['price_id'] = $price_id;

								if( $source )
								{	
									$factor = ( $row['factor'] )? $row['factor'] : $metas['change_percent'];
									$row['unit_price'] = $source[ $row['product_id'] ]['unit_price'];
									$row['unit_price'] = ( $factor )? round_to( $row['unit_price'] * ( 1 + ( $factor / 100 ) ), 2 ) : $row['unit_price'];
									$row['factor'] = ( $factor )? $factor : 0;
									$row['ref_id'] = ( $source[ $row['product_id'] ]['pr_id'] )? $source[ $row['product_id'] ]['pr_id'] : 0;
								}
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
							$price_id = $result['id'];
						}

						//ref
						$succ = $this->ref_handler( $price_id, $action, $ref );

						//source handling
						$item_ids = []; $pdt_ids = [];
						if( $succ && $metas['price_source'] )
						{
							if( $metas['apply_all'] ) $detail = [];
							$source = $this->get_source_price( $metas['price_source'], $detail );
							if( $metas['apply_all'] )
							{
								$exists = $this->Detail->get_infos( [ 'price_id'=>$price_id, 'status'=>1 ] );
								if( $exists )
								{
									foreach( $exists as $exist )
									{	
										$item_ids[] = $exist['id'];
										$pdt_ids[ $exist['product_id'] ] = $exist['id'];
									}
								}
							}
							if( ! $detail && $source )
							{
								foreach( $source as $product_id => $row )
								{
									$detail[] = [
										'product_id' => $product_id,
										'item_id' => ( $pdt_ids[ $product_id ] )? $pdt_ids[ $product_id ] : '',
										'unit_price' => $row['unit_price'],
									];
								}
							}
						}

						if( $succ && $detail )
						{
							if( ! $item_ids )
							{
								$exists = $this->Detail->get_infos( [ 'price_id'=>$price_id, 'status'=>1 ] );
								if( $exists )
								{
									foreach( $exists as $exist )
									{	
										$item_ids[] = $exist['id'];
									}
								}
							}

							$items = array();
							foreach( $detail as $i => $row )
							{
								$row['price_id'] = $price_id;

								$row['id'] = $row['item_id'];
								unset( $row['item_id'] );

								if( $source )
								{
									$factor = ( $row['factor'] )? $row['factor'] : $metas['change_percent'];
									$row['unit_price'] = $source[ $row['product_id'] ]['unit_price'];
									$row['unit_price'] = ( $factor )? round_to( $row['unit_price'] * ( 1 + ( $factor / 100 ) ), 2 ) : $row['unit_price'];
									$row['factor'] = $factor;
									$row['ref_id'] = $source[ $row['product_id'] ]['pr_id'];
								}

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

							if( $succ )	//delete ref
							{
								$succ = $this->ref_handler( $id, $action );
							}

							if( $succ ) //delete details
							{
								$outcome['id'][] = $result['id'];

								$args = [ 'price_id'=>$id ];
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

	public function ref_handler( $price_id = 0, $action = 'save', $ref = [] )
	{
		if( ! $price_id || ! $action ) return false;
		$succ = true;
		$ref_result = array();

		$detail = [];
		$item_ids = [];
		$curr_scheme = $ref['scheme'];
		$prev_scheme = '';

		if( in_array( $action, [ 'save', 'update' ] ) )
		{
			if( ! $ref ) return false;

			$ref['seller'] = ( is_array( $ref['seller'] ) )? $ref['seller'] : [ $ref['seller'] ];
			$ref['ref_id'] = ( is_array( $ref['ref_id'] ) )? $ref['ref_id'] : [ $ref['ref_id'] ];

			if( $ref['seller'] )
			{
				foreach( $ref['seller'] as $seller )
				{
					if( $ref['ref_id'] )
					{
						foreach( $ref['ref_id'] as $ref_id )
						{
							$row = $ref;
							$row['price_id'] = $price_id;
							$row['seller'] = $seller;
							$row['ref_id'] = $ref_id;
							$detail[] = $row;
						}
					}
					else
					{
						$row = $ref;
						$row['price_id'] = $price_id;
						$row['seller'] = $seller;
						$row['ref_id'] = '';
						$detail[] = $row;
					}
				}
			}
			else
				$succ = false;
		}

		if( $succ )
		{
			switch( $action )
			{
				case 'save':
				case 'update':
					$exists = $this->Ref->get_infos( [ 'price_id'=>$price_id ], [], false, [ 'usage'=>1 ] );
					if( $exists && count( $exists ) )
					{
						foreach( $exists as $exist )
						{
							$item_ids[] = $exist['id'];

							$prev_scheme = $exist['scheme'];
						}
					}

					if( $curr_scheme != $prev_scheme )
					{
						$succ = $this->ref_handler( $price_id, 'delete' );
					}

					if( $detail )
					{
						$items = array();
						foreach( $detail as $row )
						{
							foreach( $exists as $item )
							{
								if( $item['seller'] == $row['seller'] && $item['scheme'] == $row['scheme'] && $item['ref_id'] == $row['ref_id'] )
								{
									$row['id'] = $item['id'];
									break;
								}
							}

							if( ! $row['id'] || ! in_array( $row['id'], $item_ids ) )	//save
							{
								$ref = wp_parse_args( $ref, $this->get_defaultRefFields() );

								$ref_result = $this->Ref->action_handler( 'save', $row );
							}
							else if( $row['id'] && in_array( $row['id'], $item_ids ) )	//update
							{
								$ref_result = $this->Ref->action_handler( 'update', $row );
							}
							if( ! $ref_result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'error', 'error' );
								break;
							}

							if( $ref_result['id'] ) $items[] = $ref_result['id'];
						}

						//remove unneeded row
						if( $item_ids && $items )
						{
							foreach( $item_ids as $id )
							{
								if( ! in_array( $id, $items ) )
								{
									$ref_result = $this->Ref->action_handler( 'delete', [ 'id' => $id ] );

									if( ! $ref_result['succ'] )
									{
										$succ = false;
										$this->Notices->set_notice( 'error', 'error' );
										break;
									}
								}
							}
						}
					}
				break;
				case 'delete':
					$exists = $this->Ref->get_infos( [ 'price_id'=>$price_id ], [], false, [] );
					if( $exists )
					{
						foreach( $exists as $row )
						{
							$args = [ 'id' => $row['id'] ];
							$ref_result = $this->Ref->action_handler( $action, $args );
							if( ! $ref_result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}
						}
					}
				break;
			}
		}
		
		return $succ;
	}

	public function get_source_price( $source = '', $detail = [] )
	{
		$filters = [ 'seller'=>$source, 'pricing'=>'yes' ];

		$item_ids = [];
		if( $detail )
		{
			foreach( $detail as $row )
			{
				$item_ids[] = $row['product_id'];
			}
		}
		if( $item_ids ) $filters['id'] = ( is_array( $item_ids ) )? $item_ids : [ $item_ids ];

		$datas = [];
		$source = $this->Logic->get_latest_price( $filters, [], false, [ 'usage'=>1 ] );
		if( $source )
		{
			foreach( $source as $i => $item )
			{
				$datas[ $item['id'] ] = $item;
			}
		}

		return $datas;
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

				if( $handled[ $ref_id ]['status'] && $handled[ $ref_id ]['flag'] )
				{
					$refs = $this->Ref->get_infos( [ 'price_id'=>$ref_id ], [], false, [ 'usage'=>1 ] );
					$sellers = [];
					if( $refs )
					{
						foreach( $refs as $ref )
						{
							$sellers[] = $ref['seller'];
						}
						$sellers = array_unique( $sellers );
					}
					if( $sellers )
					{
						foreach( $sellers as $seller )
						{
							$succ = apply_filters( 'wcwh_sync_arrangement', $ref_id, $this->section_id, $action, $handled[ $ref_id ]['docno'], $seller );
							if( ! $succ )
							{
								$this->Notices->set_notice( 'arrange-fail', 'error' );
								break;
							}
						}
					}
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
					data-title="<?php echo $actions['save'] ?> PurchasePricing" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> PurchasePricing"
				>
					<?php echo $actions['save'] ?> PurchasePricing
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?> PurchasePricing" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?> PurchasePricing"
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
					data-title="<?php echo $actions['export'] ?> PurchasePricing" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> PurchasePricing"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
				endif;
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

		if( $ids )
		{
			$filter = [ 'uom'=>1, 'category'=>1 ];
			$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			if( $warehouse && ! $warehouse['parent'] )
			{
				$strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$warehouse['code'], 'doc_type'=>'pricing' ] );
				if( $strg_id ) $filter['inventory'] = $strg_id;
				if( $strg_id ) $args['inventory'] = $strg_id;
			}

			$items = apply_filters( 'wcwh_get_item', [ 'id'=>$ids ], [], false, $filter );
			if( $items )
			{
				$details = array();
				foreach( $items as $i => $item )
				{
					$details[$i] = array(
						'id' =>  $item['id'],
						'bqty' => '',
						'product_id' => $item['id'],
						'item_id' => '',
						'line_item' => [ 
							'name'=>$item['name'], 'code'=>$item['code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'inconsistent_unit'=>$item['inconsistent_unit'], 
							'avg_cost' => $item['avg_cost'], 'latest_cost' => $item['latest_cost']
						],
						'unit_price' => 1,
					);
				}
				$args['data']['details'] = $details;
			}
		}

		do_action( 'wcwh_get_template', 'form/purchasePricing-form.php', $args );
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
			'rowTpl'	=> $this->tplName['row'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];
		
		if( $id )
		{
			$filters = [ 'id' => $id ];
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			if( $datas )
			{
				$metas = get_pricing_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );

				$filters = [ 'price_id'=>$id ];
				if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];

				$refs = [];
				if( $datas['status'] > 0 )
					$refs = $this->Ref->get_infos( $filters, [], false, [ 'usage'=>1 ] );
				else
					$refs = $this->Ref->get_infos( $filters, [], false, [] );

				if( $refs )
				{
					foreach( $refs as $ref )
					{
						if( $ref['seller'] ) $datas['seller'][] = $ref['seller'];
						$datas['scheme'] = $ref['scheme'];
						$datas['scheme_lvl'] = $ref['scheme_lvl'];
						if( $ref['ref_id'] ) $datas['ref_id'][] = $ref['ref_id'];
					}

					$datas['seller'] = array_unique( $datas['seller'] );
					$datas['ref_id'] = array_unique( $datas['ref_id'] );
				}
				$datas['client_code'] = $datas['ref_id'];

				if( ! $datas['apply_all'] || $isView )
				{
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
					$strg_id = 0;
					if( $warehouse && ! $warehouse['parent'] )
					{
						$strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$warehouse['code'], 'doc_type'=>'pricing' ] );
					}

					$arg = [];
					if( $datas['status'] > 0 )
					{
						$arg = [ 'item'=>1, 'uom'=>1, 'usage'=>1 ];
						if( $strg_id ) $arg['inventory'] = $strg_id;
						if( $strg_id ) $args['inventory'] = $strg_id;
					}
					else
					{
						$arg = [ 'item'=>1, 'uom'=>1 ];
						if( $strg_id ) $arg['inventory'] = $strg_id;
						if( $strg_id ) $args['inventory'] = $strg_id;
					}
					$datas['details'] = $this->Detail->get_infos( $filters, [], false, $arg );
				}

				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$Inst = new WCWH_Listing();
		        	
		        if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = ($i+1).".";
		        		$datas['details'][$i]['item_name'] = $item['prdt_name'];
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];
		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'inconsistent_unit'=>$item['inconsistent_unit'],
							'avg_cost'=> $item['avg_cost'], 'latest_cost'=> $item['latest_cost']
		        		];
		        		$datas['details'][$i]['inconsistent'] = ( $item['inconsistent_unit'] )? 'Yes' : '-';
		        	}
		        }

		        $args['data'] = $datas;
				unset( $args['new'] );

				$fields = [
		        	'num' => '',
		        	'prdt_name' => 'Item',
		        	'uom_code' => 'UOM',
		        	'inconsistent' => 'Inconsistent',
		        	'avg_cost' => 'Average Cost',
		        	'latest_cost' => 'Latest Cost',
		        	'unit_price' => 'Unit Price',
		        ];
				if( ! $strg_id )
				{
					unset( $fields['avg_cost'] );
					unset( $fields['latest_cost'] );
				}
		        $args['render'] = $Inst->get_listing( $fields, 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/purchasePricing-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/purchasePricing-form.php', $args );
		}
	}

	public function view_row()
	{
		$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		$strg_id = 0;
		if( $warehouse && ! $warehouse['parent'] )
		{
			$strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$warehouse['code'], 'doc_type'=>'pricing' ] );
		}

		$args = [];
		if( $strg_id ) $args['inventory'] = $strg_id;

		do_action( 'wcwh_templating', 'segment/purchasePricing-row.php', $this->tplName['row'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/purchasePricingListing.php" ); 
			$Inst = new WCWH_PurchasePricing_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			$filters['type'] = $this->price_type;


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

	public function latest_price_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-latest-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-latest-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="wh_latest_purchase_pricing_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/purchasePriceListing.php" ); 
			$Inst = new WCWH_PurchasePrice_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->styles = [
				'#uom_code' => [ 'width' => '50px' ],
			];
			
			$Inst->advSearch = array( 'isOn'=>1 );
			$filters['pricing'] = 'yes';
			$Inst->filters = $filters;
			$Inst->advSearch_onoff();

			$Inst->bulks = array( 
				'data-modal' => 'wcwhModalForm',
				'data-actions' => 'close|submit',
				'data-title' => 'New',
				'data-tpl' => '', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$sellers = apply_filters( 'wcwh_get_warehouse', [ 'indication'=>1 ], [], false, [ 'usage'=>1 ] );
			$options = options_data( $sellers, 'code', [ 'name', 'code' ], '' );
			if( $options )
			{
				$idx = 0;
				foreach( $sellers as $i => $seller )
				{
					if( $seller['indication'] )
					{
						$idx = $i;
					}
				}
				$keys = array_keys( $options );
				$filters['seller'] = empty( $filters['seller'] )? $keys[$idx] : $filters['seller'];
			}
			$this->filters = $filters;

			$order = $Inst->get_data_ordering();
			
			$args = [ 'uom'=>1, 'group'=>1, 'category'=>1, 'brand'=>1, 'usage'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','ASC'] ];
			
			if( $filters['seller'] )
			{
				$strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$filters['seller'], 'doc_type'=>'pricing' ] );
				if( $strg_id ) $args['inventory'] = $strg_id;
			}
			if( $warehouse['parent'] ) $Inst->offCost = true;

			$datas = $this->Logic->get_latest_price( $filters, $order, false, $args );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}