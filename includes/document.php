<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Document Template
 *
 * @class    WC_DocumentTemplate
 *
 * NOTE: Document Running NO - TO be Confirmed, Document Unique
 * NOTE: FIFO - TO be Confirmed.
 * 
 */

class WC_DocumentTemplate extends WCWH_CRUD_Controller
{
	public $version = '1.0.0';
	private $_succ = false;
	protected $_tbl_document = 'document';
	protected $_tbl_document_items = 'document_items';
	protected $_tbl_document_meta = 'document_meta';
	protected $_tbl_product = 'posts'; //Product Table
	protected $_tbl_product_meta = 'postmeta'; //Product Meta Table
	protected $_tbl_reprocess_item = '';
	protected $_tbl_category = 'terms';
	protected $_tbl_uom = '';
	protected $_tbl_item_group='';
	protected $_tbl_status = '';
	protected $_tbl_transaction = '';
	protected $_tbl_transaction_items = '';
	protected $_tbl_transaction_out = '';
	protected $_tbl_inventory = '';
	protected $_tbl_storage = '';

	protected $_tbl_client = '';
	protected $_tbl_client_tree = '';
	
	private $_doc_type = "";
	private $_def_prefix = "X";
	public $_upd_uqty_flag = true; //V1.0.7
	public $_ctrl_uqty = false;
	public $_allow_empty_bqty = false;
	public $_no_details = false;
	public $_stat_to_post = 1;
	public $_allow_flagged_edit = false; 

	/*
	 *	Default Data for submission
	 */
	protected $header_defaults = array();
	protected $item_defaults = array();

	/*
	 *	Submitted Data , Posted Data
	 */
	protected $header_item = array();
	protected $detail_item = array();
	protected $parent_status = array();
	protected $allowed_status = array(); //V1.0.3
	
	protected $stat = array( 
		'trash' => 0,
		'initial' => 1,
		'confirm' => 1,
		'refute' => 1,
		'post' => 6,
		'unpost' => 1,
		'complete' => 9,
		'incomplete' => 6,
		'close' => 10,
		'reopen' => 9,
	);

	protected $_doc_exclude_period = [ 'account_period' ];
	protected $_action_exclude_period = [ 'complete' ];

	public $processing_stat = [];

	public $useFlag = false;

	protected $warehouse = array();

	private $temp_data = array();
	
	/**
	 * Constructor 
	 */
	public function __construct() 
	{
		parent::__construct();
		
		global $wcwh, $wpdb;
		$prefix = $this->get_prefix();
		$this->_tbl_document = $prefix."document";
		$this->_tbl_document_items = $prefix."document_items";
		$this->_tbl_document_meta = $prefix."document_meta";
		$this->_tbl_product = $prefix."items";
		$this->_tbl_product_meta = $prefix."itemsmeta";
		$this->_tbl_reprocess_item = $prefix."reprocess_item";
		$this->_tbl_category = $wpdb->prefix."terms";
		$this->_tbl_uom = $prefix."uom";
		$this->_tbl_item_group = $prefix."item_group";
		$this->_tbl_status = $prefix."status";
		$this->_tbl_transaction = $prefix."transaction";
		$this->_tbl_transaction_items = $prefix."transaction_items";
		$this->_tbl_transaction_out = $prefix."transaction_out_ref";
		$this->_tbl_inventory = $prefix."inventory";
		$this->_tbl_storage = $prefix."storage";

		$this->_tbl_client = $prefix."client";
		$this->_tbl_client_tree = $prefix."client_tree";

		$this->header_defaults = array(
			'warehouse_id' 		=> '',
			'docno'  			=> '',  //Document No
			//'sdocno'  		=> '',  //System Generated Document No
			'doc_type' 			=> '',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
			'doc_date' 			=> '',  //Document Date
			'post_date'			=> '',	//Posting Date
			//'status' 			=> '1', //Default 1
			//'flag'			=> '0',
			'parent'			=> '0',
			//'created_by' 		=> '',
			//'created_at' 		=> '',
			'lupdate_by' 		=> '',
			'lupdate_at' 		=> '',
		);
		$this->item_defaults = array(
			'doc_id'  			=> '',  //Header Link ID
			'strg_id'			=> '0',  
			'product_id' 		=> '', //Document Post ID
			'uom_id' 			=> '',  //Document Type, Eg: Transfer In, Transfer Out, or Delivery Order
			'bqty' 				=> '0', //Base Qty
			//'uqty' 				=> '0', //Used Qty
			'bunit'				=> '0',
			'ref_doc_id' 		=> '0', //linked parent header
			'ref_item_id' 		=> '0',  //linked parent item
			//'_item_number'	=> '0',
			//'status' 			=> '1', //Default 1
			//'created_by' 		=> '',
			//'created_at' 		=> '',
			'lupdate_by' 		=> '',
			'lupdate_at' 		=> '',
		);

		$this->header_def = array(
			'doc_id',
			'warehouse_id',
			'docno',  
			'sdocno',  
			'doc_type',  
			'doc_date',  
			'post_date',	
			'status', 
			'flag',
			'parent',
			'created_by',
			'created_at',
			'lupdate_by',
			'lupdate_at',
		);
		$this->item_def = array(
			'item_id',
			'doc_id',  
			'strg_id', 
			'product_id', 
			'uom_id',  
			'bqty', 
			'uqty',
			'bunit',
			'ref_doc_id', 
			'ref_item_id', 
			'status', 
			'created_by',
			'created_at',
			'lupdate_by',
			'lupdate_at',
			'ref_bqty',
			'ref_base',
			'ref_bal',
		);
		$this->user_id = get_current_user_id();
		$this->init_hooks();
	}
	/**
	 *	Destruct
	 */
	public function __destruct() 
	{
        remove_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10 );
    }
	/**
	 *	Instance for one time only
	 *	@return	object	instance
	 */
	public static function instance()
	{
        if ( is_null( self::$_instance ) ) 
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
	/**
	 * init hooks
	 */
	private function init_hooks() 
	{
		add_action( 'document_template_action_handle', array( $this, 'document_action_handle' ) ,5 , 3 ); // V1.0.2

		add_filter( 'wcwh_docno_replacer', array( $this, 'docno_replacer' ), 10, 3 );
	}
	/**
	 * Diff DB Name
	 */
	protected function dbName()
	{
		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] && $this->warehouse['dbname'] )
		{
			return $this->warehouse['dbname'].".";
		}

		return '';
	}
	/**
	 *	Set Document Type
	 */
	public function setDocumentType( $doc_type )
	{
        $this->_doc_type = $doc_type ;
    }
	/**
	 *	Set Document Prefix
	 */
	public function setDocumentPrefix( $doc_prefix )
	{
        $this->_def_prefix = $doc_prefix;
    }
	/**
	 *	Set Update Uqty Flag //V1.0.7
	 */
	public function setUpdateUqtyFlag( $flag )
	{
        $this->_upd_uqty_flag = $flag ;
    }
    /**
	 *	Set Status
	 */
    public function setStat( $stats )
    {
    	$this->stat = $stats;
    }
    /**
	 *	Set warehouse data
	 */
    public function setWarehouse( $wh )
    {
    	$this->warehouse = $wh;
    }
    /**
	 *	Set Account Period Exclusive by Doc
	 */
	public function setAccPeriodExclusive( $doc_types )
	{
        $this->_doc_exclude_period = array_merge( $this->_doc_exclude_period, $doc_types );
    }
    /**
	 *	Set Account Period Exclusive by Action
	 */
	public function setActionPeriodExclusive( $actions )
	{
        $this->_action_exclude_period = array_merge( $this->_action_exclude_period, $actions );
    }
	/**
	 *	Get Document Type V1.0.2
	 */
	public function getDocumentType()
	{
        return $this->_doc_type;
    }
    /**
	 *	Get Parent Document Status
	 */
    public function getParentStatus()
    {
    	return $this->parent_status;
    }
    /**
	 *	Get Status
	 */
    public function getStat()
    {
    	return $this->stat;
    }
    /**
	 *	Get Account Period Exclusive by Doc
	 */
	public function getAccPeriodExclusive()
	{
        return $this->_doc_exclude_period;
    }
    /**
	 *	Get Account Period Exclusive by Action
	 */
	public function getActionPeriodExclusive()
	{
        return $this->_action_exclude_period;
    }
    /**
	 *	Get Header Item After Handler
	 */
    public function getHeaderItem()
    {
    	return $this->header_item;
    }
    /**
	 *	Get Detail Item After Handler
	 */
    public function getDetailItem()
    {
    	return $this->detail_item;
    }
	/**
	 *	System Generated Document No.
	 *	KIV: TO BE CONFIRMED
	 */
	public function get_document_running_no( $header_item )
	{
		$doc_prefix = $this->_def_prefix;
		$sdocno = $doc_prefix.current_time( 'YmdHis' );

		return apply_filters( 'warehouse_generate_docno', $sdocno, $this->_doc_type, $doc_prefix );
	}
	public function docno_replacer( $sdocno, $doc_type = '', $opts = [] )
	{
		if( $doc_type && $doc_type == $this->_doc_type )
		{	
			$datas = $this->temp_data;
			$ref = array();
			
			if( $datas['ref_doc_id'] > 0 && $datas['ref_doc'] )
			{
				$docno_count = get_document_meta( $datas['ref_doc_id'], "{$this->_doc_type}_docno_nextno", 0, true );
				$docno_count++;
				$find = [ 
					'ref_doc' => '{ref_doc}',
					'ref_count' => '{ref_count}',
				];
				$replace = [ 
					'ref_doc' => ( $datas['ref_doc'] )? $datas['ref_doc'] : '',
					'ref_count' => str_pad( $docno_count, 2, "0", STR_PAD_LEFT )
				];

				$sdocno = str_replace( $find, $replace, $sdocno );

				update_document_meta( $datas['ref_doc_id'], "{$this->_doc_type}_docno_nextno", $docno_count, 0 );
			}
		}

		return $sdocno;
	}

	public function get_header_defaults()
	{
		return $this->header_defaults;
	}

	public function get_item_defaults()
	{
		return $this->item_defaults;
	}

	/*
	 *	Check stocktake
	 */
	public function check_stocktake( $action = '', $header = array(), $block_actions = [ 'post', 'unpost' ] )
	{
		$continue = true;
		if( ! $action || ! $header['doc_id'] ) return $continue;

		$block_actions = empty( $block_actions )? [ 'post', 'unpost' ] : $block_actions;
		if( in_array( $action, $block_actions ) )
		{
			$filter = [ 'warehouse_id'=>$header['warehouse_id'], 'doc_type'=>'stocktake', 'status'=>3 ];
			$args = [ 'meta'=>[ 'stocktake_item' ] ];
			$stocktake = $this->get_header( $filter, [], true, $args );
			if( $stocktake )
			{
				if( in_array( $stocktake['stocktake_item'], [ 'apply_all', 'store_type' ] ) )
				{
					$continue = false;
					if( $this->Notices ) $this->Notices->set_notice( "Not allowed, StockTake or Adjustment in action!!", "error", $this->_doc_type."|document_action_handle" );
				}
				else
				{
					$details = $this->get_detail( [ 'doc_id' => $header['doc_id'] ], [], false );
					if( $details )
					{
						$prdt = [];
						foreach( $details as $i => $row )
						{
							$prdt[] = $row['product_id'];
						}

						$filter = [ 'doc_id'=>$stocktake['doc_id'], 'product_id'=> $prdt ];
						$stocktake_details = $this->get_detail( $filter, [], false, [ 'usage'=>1 ] );
						if( $stocktake_details && count( $stocktake_details ) > 0 )
						{
							$continue = false;
							if( $this->Notices ) $this->Notices->set_notice( "Not allowed, StockTake or Adjustment in action!!", "error", $this->_doc_type."|document_action_handle" );
						}
					}
				}
			}
		}

		return $continue;
	}
	#-----------------------------------------------------------------#
	#	>	Basic Document Updates
	#-----------------------------------------------------------------#	
	/**
	 *	Action Handle
	 */
	public function document_action_handle( $action , $header = array() , $details  = array() )
	{
		if( $this->Notices ) $this->Notices->reset_operation_notice();
		$succ = true;
		$this->user_id = !empty( $this->user_id )? $this->user_id : get_current_user_id();
		$this->user_id = empty( $this->user_id )? 0 : $this->user_id;
		$parent_doc = array();  //Affected Linked Document
		$action = strtolower( $action );

		$doc_time = ( $header['doc_time'] )? $header['doc_time'] : '';
		$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'], $doc_time ) : current_time( 'mysql' );
		
		//Check Accounting Period V1.0.3
		$validDate = $this->document_account_period_handle( $header['doc_id'], $header['posting_date'], $header['warehouse_id'], $action );
		if( ! $validDate )
		{
			if( $this->Notices ) $this->Notices->set_notice( "Not Allowed. Date is out of Accounting Period!", "warning", $this->_doc_type."|document_action_handle" );
			return false;
		}
		
		switch ( $action )
		{
			case "save":
			case "save-post":
			case "update":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				$header_item['doc_type'] = !empty( $header['doc_type'] )? $header['doc_type'] : $this->_doc_type;
				$header_item['lupdate_by'] = $this->user_id;
				$header_item['lupdate_at'] = current_time( 'mysql' );

				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					//New Created
					$this->temp_data = $header;
					$header_item['sdocno'] = !empty( $header['sdocno'] )? $header['sdocno'] : $this->get_document_running_no( $header_item );
					$this->temp_data = array();
					$header_item['docno'] = empty( $header_item['docno'] ) ? $header_item['sdocno'] : $header_item['docno'];
					$header_item['status'] = isset( $header['hstatus'] )? $header['hstatus'] : 1;
					$header_item['flag'] = ( $this->useFlag )? 0 : 1;
					$header_item['flag'] = isset( $header['hflag'] )? $header['hflag'] : $header_item['flag'];
					$header_item['created_by'] = $this->user_id;
					$header_item['created_at'] = current_time( 'mysql' );
					if( $action == 'save-post' ) 
					{
						$header_item['post_date'] = ( !empty( (int)$header['posting_date'] ) )? date_formating( $header['posting_date'] ) : current_time( 'mysql' );
						$header_item['post_date'] = !empty( (int)$header_item['post_date'] )? $header_item['post_date'] : current_time( 'mysql' );
					}
					$doc_id = $this->add_document_header( $header_item );
					if( ! $doc_id )
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "create-fail", "error", $this->_doc_type.$this->_doc_type."|document_action_handle" );
					}
					$header['doc_id']= $doc_id;

					if( isset( $header_item['hstatus'] ) ){ unset( $header['hstatus'] ); unset( $header_item['hstatus'] ); } 
					$header_item['doc_id'] = $doc_id;
					if( $succ )
					{
						$itm_cnt = 1; //V1.0.6
						//Add Document item
						if( $details && ! $this->_no_details )
						{
							$details = $this->document_items_sorting( $details );
							foreach ( $details as $detail_item )
							{
								$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
								if( ! $this->_allow_empty_bqty && $ditem['bqty'] <= 0 ) continue;
								$ditem['doc_id'] = $header_item['doc_id'];
								$ditem['lupdate_by'] = $this->user_id;
								$ditem['lupdate_at'] = current_time( 'mysql' );
								$ditem['status'] = isset( $detail_item['dstatus'] )? $detail_item['dstatus'] : 1;
								$ditem['created_by'] = $this->user_id;
								$ditem['created_at'] = current_time( 'mysql' );
								$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );
								$ditem['uom_id'] = !empty( $ditem['uom_id'] )? $ditem['uom_id'] : '';
								
								if( isset( $ditem['ref_doc_id'] ) && ! $ditem['ref_doc_id'] ) $ditem['ref_doc_id'] = "0";
								if( isset( $ditem['ref_item_id'] ) && ! $ditem['ref_item_id'] ) $ditem['ref_item_id'] = "0";
								
								$detail_id = $this->add_document_items( $ditem );
								if( ! $detail_id )
									$succ = false;
								$ditem['item_id'] = $detail_id;

								$detail_item['item_id']= $ditem['item_id'];
								$detail_item['_item_number'] = $itm_cnt++; //V1.0.6
								$detail_item['strg_id'] = $ditem['strg_id'];
								if( isset( $detail_item['dstatus'] ) ) unset( $detail_item['dstatus'] );
								
								if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) // V1.0.2 //V1.0.7
								{
									$succ = $this->update_items_uqty_handles( $exist_item , $ditem );
									$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
									if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
									{
										$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
									}
								}

								$this->detail_item[] = $detail_item;
							}
						}
					}
				}
				else  //UPDATE
				{
					//Validation on Document Status V1.0.3
					$exist_header = $this->get_document_header( $header_item['doc_id'] ); 
					if( ! $exist_header )
					{
						if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
						$succ = false;
					} 
					else if ( $exist_header == "0" )
					{
						if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
						$succ = false;
					}
					else if ( count( $this->allowed_status ) > 0 && ! in_array( $exist_header['status'] , $this->allowed_status ) )
					{
						if( $this->Notices ) $this->Notices->set_notice( "Current document not allow to edit", "warning", $this->_doc_type."|document_action_handle" );
						$succ = false;
					}
					if( $succ && $this->useFlag && $exist_header['flag'] != 0 && ! $this->_allow_flagged_edit && 
						!current_user_cans( [ 'wh_support', 'wh_admin_support' ] ) )
					{
						if( $this->Notices ) $this->Notices->set_notice( "Current document not allow to edit", "warning", $this->_doc_type."|document_action_handle" );
						$succ = false;
					}

					if( $succ ) //V1.0.3
					{
						$header_item['docno'] = empty ( $header_item['docno'] ) ? $exist_header['docno'] : $header_item['docno']; //V1.0.3 
						$header_item['docno'] = empty ( $header_item['docno'] ) ? $exist_header['sdocno'] : $header_item['docno']; //V1.0.3 
						$header_defaults = $this->header_defaults; unset( $header_defaults['post_date'] );
						$upd_header = array_map_key( $header_item , $header_defaults ); //V1.0.3 
						$succ = $this->update_document_header( array ( 'doc_id' => $header_item['doc_id'] ) , $upd_header );
					}

					if( $succ )
					{
						$active_item = array(); //For deletion on non active items
						$arr_item_id = array(); //Existing updated item_id
						$exist_items = array(); //Existing updated items

						//Get Submitted item_id - V1.0.1
						if( $details )
						{
							foreach ( $details as $detail_item )
							{
								if( isset( $detail_item['item_id'] ) && ! empty( $detail_item['item_id'] ) ) //V1.0.3
								{
									$arr_item_id[] = $detail_item['item_id'];
								}
							}
						}
						//Get Existing Item - V1.0.1
						if( count($arr_item_id) > 0 )
						{
							$exist_items_arr = $this->get_exists_document_items_by_item_id( $arr_item_id );
							foreach ( $exist_items_arr as $exist_ditems )
							{
								$exist_items[ $exist_ditems['item_id'] ] = $exist_ditems;
							}
						}

						$itm_cnt = 1; //V1.0.6
						//Update Document item
						if( $details && ! $this->_no_details )
						{
							$details = $this->document_items_sorting( $details );
							foreach ( $details as $detail_item )
							{
								$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
								$ditem['doc_id'] = $header_item['doc_id'];
								$ditem['lupdate_by'] = $this->user_id;
								$ditem['lupdate_at'] = current_time( 'mysql' );

								//fix ref null issue
								$ditem['ref_doc_id'] = ( isset( $ditem['ref_doc_id'] ) && $ditem['ref_doc_id'] == "" )? 0 : $ditem['ref_doc_id'];
								$ditem['ref_item_id'] = ( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] == "" )? 0 : $ditem['ref_item_id'];

								//Check Exists Item - V1.0.1
								if( isset( $ditem['item_id'] ) && $ditem['item_id'] != "" )
								{
									$exist_item = $exist_items[ $ditem['item_id'] ];
								}	
								else 
								{
									$exist_item = $this->get_exists_document_items( $ditem['doc_id'] , $ditem['product_id'] ,$ditem['ref_doc_id'] ,$ditem['ref_item_id'] , $ditem['block']);	
								}
								if( ! $exist_item )
								{
									$ditem['status'] = isset( $detail_item['dstatus'] )? $detail_item['dstatus'] : 1;
									$ditem['created_by'] = $this->user_id;
									$ditem['created_at'] = current_time( 'mysql' );
									$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );

									$detail_id = $this->add_document_items( $ditem );
									if( ! $detail_id )
										$succ = false;
									$ditem['item_id'] = $detail_id;
									$detail_item['strg_id'] = $ditem['strg_id'];
								}
								else 
								{
									$upd_item = array_map_key( $ditem , $this->item_defaults );
									$upd_item['strg_id'] = ( $exist_item['strg_id'] > 0 )? $exist_item['strg_id'] : apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );
									if ( ! $this->update_document_items( array ( 'item_id' => $exist_item['item_id']) , $upd_item ) )
									{
										$succ = false;
									}
									$ditem['item_id'] = $exist_item['item_id']; //V1.0.3
									$ditem['strg_id'] = $upd_item['strg_id'];
									$detail_item['strg_id'] = $ditem['strg_id'];
								}
								//UPDATE Used Qty & Status - V1.0.1
								if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) //V1.0.2 //V1.0.7
								{
									$succ = $this->update_items_uqty_handles( $exist_item , $ditem );
									$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
									if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
									{
										$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
									}
								}

								$active_item[] = $ditem['item_id'];
								$detail_item['item_id']= $ditem['item_id'];
								if( ! isset( $detail_item['_item_number'] ) || $detail_item['_item_number'] <= 0 || $detail_item['_item_number'] == '' ) 
									$detail_item['_item_number'] = $itm_cnt++;; //V1.0.6
								
								$this->detail_item[] = $detail_item;
							}
						}
						
						//Remove deleted item
						if( $succ && ! $this->_no_details )
						{
							$exists_delete_item = $this->get_deletion_document_items( $header_item['doc_id'] , $active_item ); //Item to be deleted

							if ( $exists_delete_item )
							{
								//OFFSET uqty - V1.0.1
								if( $this->_upd_uqty_flag )  //V1.0.7 add condition
									$succ = $this->deleted_items_uqty_handles( $exists_delete_item );
								if( $succ )
									$succ = $this->delete_document_items( $header_item['doc_id'] , $active_item );
							}
						}
					}
				}

				if( $this->_upd_uqty_flag && $succ && count( $parent_doc ) > 0 ) //V1.0.7 add condition
				{
					//Check if valid uqty updated
					if( $this->_ctrl_uqty )
					{
						$invalid_records = $this->get_incorrect_uqty_updates( $parent_doc );
						if ( isset( $invalid_records) && count($invalid_records) > 0 )
							$succ = false;
					}
					//UPDATE Linked Status - V1.0.1
					if( $succ )
					{
						$succ = $this->update_document_header_status_handles( $parent_doc );
					}
				}
				$exist_header = $this->get_document_header( $header_item['doc_id'] );
				$this->header_item = wp_parse_args( $header, $exist_header ); 
			break;
			case "delete":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				$exists = $this->get_document_header( $header_item['doc_id'], "1" );
				if( ! $exists )
				{
					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				/*if( $succ && $this->useFlag && $exists['flag'] > 0 )
				{
					if( $this->Notices ) $this->Notices->set_notice( "Not allow to delete", "warning", $this->_doc_type."|document_action_handle" );
					$succ = false;
				}*/
				if( $succ )
				{
					$del_item = array();
					$del_item['status'] = $this->stat['trash'];
					$del_item['lupdate_by'] = $this->user_id;
					$del_item['lupdate_at'] = current_time( 'mysql' );
					//Inactive Header
					$succ = $this->update_document_header( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'] ) , $del_item ); //V1.0.3

					//OFFSET uqty - V1.0.1
					$exists_delete_item = $this->get_deletion_document_items( $header_item['doc_id'] ); //Item to be deleted
					if ( $succ && $exists_delete_item && ! $this->_no_details )
					{
						if( $this->_upd_uqty_flag )  //V1.0.7 add condition
							$succ = $this->deleted_items_uqty_handles( $exists_delete_item );
						//Inactive Item
						if( $succ )
							$succ = $this->update_document_items( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'] ) , $del_item );//V1.0.3
					}
					$exists['status'] = $del_item['status'];
				}
				$this->header_item = $exists;
				$this->detail_item = $this->get_document_items_by_doc( $this->header_item['doc_id'] );
			break;
			case "post": //V1.0.2 Post Action
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				$exists = $this->get_document_header( $header_item['doc_id'] , $this->_stat_to_post, "1" );
				if( ! $exists )
				{
					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				else 
				{
					$post_item = array();
					$post_item['status'] = $this->stat['post'];
					$post_item['lupdate_by'] = $this->user_id;
					$post_item['lupdate_at'] = current_time( 'mysql' );
					
					$post_header = $post_item;
					$post_header['post_date'] = ( !empty( (int)$exists['posting_date'] ) )? date_formating( $exists['posting_date'] ) : current_time( 'mysql' );
					$post_header['post_date'] = !empty( (int)$post_header['post_date'] )? $post_header['post_date'] : current_time( 'mysql' );
					//Post Header
					$succ = $this->update_document_header( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_header );//V1.0.3
					if( $succ && ! $this->_no_details )
					{
						//Post Item
						$succ = $this->update_document_items( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_item );//V1.0.3
					}

				}
				$this->header_item = $exists; // = $header; V1.0.4
				$this->detail_item = $this->get_document_items_by_doc( $this->header_item['doc_id'] );
			break;
			case "unpost": //V1.0.3 Un-Post Action
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				$exists = $this->get_document_header( $header_item['doc_id'] , $this->stat['post'] ); //Only Posted Status can be Unpost
				if( ! $exists )
				{
					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				else 
				{
					$post_item = array(); $post_header = array();
					$post_item['status'] = $this->_stat_to_post;
					$post_item['lupdate_by'] = $this->user_id;
					$post_item['lupdate_at'] = current_time( 'mysql' );
					
					$post_header = $post_item;
					if( $this->useFlag ) $post_header['flag'] = 0;
					//Post Header
					$succ = $this->update_document_header( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_header );
					if( $succ && ! $this->_no_details )
					{
						//Post Item
						$succ = $this->update_document_items( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_item );
					}
				}
				$this->header_item = $exists; // = $header; V1.0.4
				$this->detail_item = $this->get_document_items_by_doc( $this->header_item['doc_id'] );
			break;
			case "confirm":
			case "refute":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				$exists = $this->get_document_header( $header_item['doc_id'] );
				if( ! $exists )
				{
					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				else 
				{
					$act_item = array();
					$act_item['status'] = $this->stat[$action];
					$act_item['lupdate_by'] = $this->user_id;
					$act_item['lupdate_at'] = current_time( 'mysql' );
					//Inactive Header
					$succ = $this->update_document_header( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'], 'flag' => $exists['flag'] ) , $act_item ); //V1.0.3
				}
				$this->header_item = $exists;
				$this->detail_item = $this->get_document_items_by_doc( $this->header_item['doc_id'] );
			break;
			case "approve":
			case "reject":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				$exists = $this->get_document_header( $header_item['doc_id'], "1", "0" );
				if( ! $exists )
				{
					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				else 
				{
					$act_item = array();
					$act_item['status'] = 1;
					$act_item['flag'] = $header_item['flag'];
					$act_item['lupdate_by'] = $this->user_id;
					$act_item['lupdate_at'] = current_time( 'mysql' );
					//Inactive Header
					$succ = $this->update_document_header( array( 'doc_id' => $header_item['doc_id'] , 'status' => $exists['status'], 'flag' => $exists['flag'] ) , $act_item ); //V1.0.3
				}
				$exists['flag'] = $act_item['flag'];
				$this->header_item = $exists;
				$this->detail_item = $this->get_document_items_by_doc( $this->header_item['doc_id'] );
			break;
			case "complete":
			case "incomplete":
			case "close":
			case "reopen":
			case "trash":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) )
				{
					if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				$exists = $this->get_document_header( $header_item['doc_id'] );
				if( ! $exists )
				{
					if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->_doc_type."|document_action_handle" );
					return false;
				}
				else 
				{
					$post_item = array();
					$post_item['status'] = $this->stat[$action];
					$post_item['lupdate_by'] = $this->user_id;
					$post_item['lupdate_at'] = current_time( 'mysql' );
					
					$post_header = $post_item;
					//Post Header
					$succ = $this->update_document_header( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_header );//V1.0.3
					if( $succ && ! $this->_no_details )
					{
						//Post Item
						$succ = $this->update_document_items( array( 'doc_id' => $header_item['doc_id'], 'status' => $exists['status'] ) , $post_item );//V1.0.3
					}

				}
				$this->header_item = $exists; // = $header; V1.0.4
				$this->detail_item = $this->get_document_items_by_doc( $this->header_item['doc_id'] );
			break;
			case "delete-item":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) || count( $details ) == 0 )
				{
					return false;
				}
				$arr_item = array();
				//GET Item ID
				foreach ( $details as $detail_item )
				{
					$arr_item[ $detail_item['item_id']] = $detail_item['item_id'];
				}

				$exists_delete_item = $this->get_exists_document_items_by_item_id( $arr_item ); //Item to be deleted
				if ( $exists_delete_item )
				{
					//OFFSET uqty - V1.0.1
					if( $this->_upd_uqty_flag )  //V1.0.7 add condition
						$succ = $this->deleted_items_uqty_handles( $exists_delete_item );
					if( $succ )
					{
						$succ = $this->delete_document_items( $header_item['doc_id'] , $arr_item , false );

						$item_status = $this->get_distinct_document_item_status( array( $header_item['doc_id'] ) );
						if( $item_status ) // V1.0.3
						{
							//Get Document Status
							foreach( $item_status as $item )
							{
								$sta_array = explode( "," , $item['status'] );
								if( count( $sta_array ) == 1 )
								{
									//Update Document Status = Item Status
									$sta_doc_arr[ $sta_array[0] ][] = $item['doc_id'];
								}
								else 
								{
									//More than 1 status = Partial Status
									$sta_doc_arr[ $this->parent_status['partial'] ][] = $item['doc_id'];
								}
							}
						}
						else
						{
							$sta_doc_arr[0][] = $header_item['doc_id'];
						}
						//Update Document Status = Item Status
						if( count( $sta_doc_arr ) > 0 )
						{
							foreach( $sta_doc_arr as $status => $arr_doc_id )
							{	
								if( ! $this->update_document_status( $arr_doc_id , $status ) )
									$succ = false; 
							}
						}
					}
				}
				else
				{
					//NO VALID RECORD FOUND
					$succ = false;
				}
				$this->header_item = $header;
			break;
			case "update-item":
				$header_item = wp_parse_args( $header, $this->header_defaults ); 
				$header_item['doc_type'] = $this->_doc_type;
				if( ! $header_item['doc_id'] || empty($header_item['doc_id']) || count( $details ) == 0 )
				{
					return false;
				}
				$exist_header = $this->get_document_header( $header_item['doc_id'] );
				if( ! $exist_header )
				{
					return false;
				}
				
				$arr_item = array();
				$exist_item = array();
				//GET Item ID
				foreach ( $details as $detail_item )
				{
					$arr_item[ $detail_item['item_id']] = $detail_item['item_id'];
				}
				$exists = $this->get_exists_document_items_by_item_id( $arr_item ); 
				if ( $exists )
				{	
					foreach ( $exists as $item )
					{
						$exist_item[ $item['item_id']] = $item;
					}
					foreach ( $details as $detail_item )
					{ 
						$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
						$exist = $exist_item[ $ditem['item_id'] ];

						$ditem['doc_id'] = $header_item['doc_id'];
						$ditem['lupdate_by'] = $this->user_id;
						$ditem['lupdate_at'] = current_time( 'mysql' );
						
						//fix ref null issue
						$ditem['ref_doc_id'] = ( isset( $ditem['ref_doc_id'] ) && $ditem['ref_doc_id'] == "" )? 0 : $ditem['ref_doc_id'];
						$ditem['ref_item_id'] = ( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] == "" )? 0 : $ditem['ref_item_id'];
						
						if( ! $exist )
						{
							$ditem['status'] = 1;
							$ditem['created_by'] = $this->user_id;
							$ditem['created_at'] = current_time( 'mysql' );
							$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );

							$detail_id = $this->add_document_items( $ditem );
							if( ! $detail_id )
								$succ = false;
							$ditem['item_id'] = $detail_id;
							$detail_item['strg_id'] = $ditem['strg_id'];
						}
						else
						{
							$upd_item = array_map_key( $ditem , $this->item_defaults );
							unset( $upd_item['strg_id'] );
							if ( ! $this->update_document_items( array ( 'item_id' => $exist['item_id']) , $upd_item ) )
							{
								$succ = false;
							}
							$ditem['item_id'] = $exist['item_id']; //V1.0.3
							$ditem['strg_id'] = $exist['strg_id'];
							$detail_item['strg_id'] = $ditem['strg_id'];
						}

						//UPDATE Used Qty & Status
						if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) //V1.0.2 //V1.0.7
						{
							$succ = $this->update_items_uqty_handles( $exist , $ditem );
							$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
							if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
							{
								$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
							}
							if( ! $succ )
							{
								break;
							}
						}
						
						$detail_item['item_id']= $ditem['item_id'];
						$this->detail_item[] = $detail_item;	//idw added	for inventory allocation handler
					}
				}
				else 
				{
					foreach ( $details as $detail_item )
					{ 
						$ditem = wp_parse_args( $detail_item, $this->item_defaults ); 
						$ditem['doc_id'] = $header_item['doc_id'];
						$ditem['lupdate_by'] = $this->user_id;
						$ditem['lupdate_at'] = current_time( 'mysql' );
						
						//fix ref null issue
						$ditem['ref_doc_id'] = ( isset( $ditem['ref_doc_id'] ) && $ditem['ref_doc_id'] == "" )? 0 : $ditem['ref_doc_id'];
						$ditem['ref_item_id'] = ( isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] == "" )? 0 : $ditem['ref_item_id'];
						
						$ditem['status'] = 1;
						$ditem['created_by'] = $this->user_id;
						$ditem['created_at'] = current_time( 'mysql' );
						
						$ditem['strg_id'] = apply_filters( 'wcwh_get_system_storage', $ditem['strg_id'], $header_item, $ditem );
						
						$detail_id = $this->add_document_items( $ditem );
						if( ! $detail_id )
							$succ = false;
						$ditem['item_id'] = $detail_id;
						$detail_item['strg_id'] = $ditem['strg_id'];

						//UPDATE Used Qty & Status
						if( $this->_upd_uqty_flag && isset( $ditem['ref_item_id'] ) && $ditem['ref_item_id'] != "0" ) //V1.0.2 //V1.0.7
						{
							$succ = $this->update_items_uqty_handles( $exist , $ditem );
							$parent_doc[$ditem['ref_doc_id']] = $ditem['ref_doc_id'];
							if( $exist_item['ref_doc_id'] != "" && $exist_item['ref_doc_id'] != $ditem['ref_doc_id'] )
							{
								$parent_doc[$exist_item['ref_doc_id']] = $exist_item['ref_doc_id'];
							}
							if( ! $succ )
							{
								break;
							}
						}
						
						$detail_item['item_id']= $ditem['item_id'];
						$this->detail_item[] = $detail_item;	//idw added	for inventory allocation handler
					}
				}
				if( $this->_upd_uqty_flag && $succ && count( $parent_doc ) > 0 ) //V1.0.7
				{
					//Check if valid uqty updated
					if( $this->_ctrl_uqty )
					{
						$invalid_records = $this->get_incorrect_uqty_updates( $parent_doc );
						if ( isset( $invalid_records) && count($invalid_records) > 0 )
							$succ = false;
					}
					//UPDATE Linked Status - V1.0.1
					if( $succ )
					{
						$succ = $this->update_document_header_status_handles( $parent_doc );
					}
				

				}
				$this->header_item = $header;	//idw added	for inventory allocation handler
			break;
		}
		//results_table( $this->get_deletion_document_items( $header_item['doc_id'] ) );
		//echo "<br />".$succ."--".$action."--". $this->_doc_type. " --->BBBBB"; exit;
		
		//idw_added - to allow further action through hook
		$succ = apply_filters( 'warehouse_after_'.$this->getDocumentType().'_document_action', $succ, $action, $this->header_item, $this->detail_item, $exist_items );
		
		return $succ;
	}
	/**
	 *	Details Sorting
	 */
	public function document_items_sorting( $details )
	{
		return $details;
	}
	/**
	 *	Import Handler
	 */
	public function import_handler( $action = 'save', $header = array(), $details = array() )
	{
		if( ! $header || ! $details ) return false;
		$succ = true;
		
		$exists = $this->get_header( [ 'warehouse_id'=>$header['warehouse_id'], 'sdocno'=>$header['sdocno'] ], [], true, [ 'usage'=>1 ] );
		if( $exists ) return $succ;
		
		$this->header_item = [];
		$this->detail_item = [];
		
		$succ = $this->document_action_handle( 'save' , $header , $details );
		if( ! $succ )
		{
			return false;
		}
		$doc_id = $this->header_item['doc_id'];
		$header_item = $this->header_item;
		$detail_items = $this->detail_item;
		
		unset( $header_item['hstatus'] );
		unset( $header_item['hflag'] );
		unset( $header_item['sdocno'] );
		foreach( $detail_items as $i => $detail_item )
		{
			unset( $detail_items[$i]['dstatus'] );
		}
		
		//Header Custom Field
		$succ = $this->header_meta_handle( $doc_id, $header_item );
		$succ = $this->detail_meta_handle( $doc_id, $detail_items );
		
		return $succ;
	}
	/**
	 *	Header Meta Handle V1.0.3
	 */
	public function header_meta_handle( $doc_id, $header_item, $sanitize = false )
	{
		if( !$doc_id || !$header_item ) return;
		
		$succ = true;
		$header_keys = $this->header_def;
		
		foreach( $header_item as $key => $value )
		{
			if( !in_array( $key, $header_keys ) )
			{
				$value = isset( $value ) ? $value : '';
				$value = ( $sanitize )? sanitize_text_field( $value ) : $value;
				if( ! $this->add_document_meta_value( $key , $value , $doc_id ) )
				{
					$succ = false;
					break;
				}
			}
		}
		
		return $succ;
	}
	/**
	 *	Detail Meta Handle V1.0.3
	 */
	public function detail_meta_handle( $doc_id, $detail_item, $sanitize = false ){
		if( !$doc_id || !$detail_item ) return;
		
		$succ = true;
		$detail_keys = $this->item_def;
		
		foreach ( $detail_item as $items ){
			if( $items ){
				foreach( $items as $key => $value ){
					$value = isset( $value ) ? $value : '';
					$value = ( $sanitize )? sanitize_text_field( $value ) : $value;
					if( !in_array( $key, $detail_keys ) ){
						if( ! $this->add_document_meta_value( $key , $value , $doc_id, $items['item_id'] ) )
						{
							$succ = false;
							break;
						}
					}
				}
			}
		}
		
		return $succ;
	}
	/**
	 *	Document Meta Handle V1.0.3
	 */
	public function add_document_meta_value( $meta_key , $meta_value , $doc_id, $item_id = 0 ){
		global $wpdb;
		$succ = true;

		if( ! $doc_id )
		{
			return false;
		}
		$exists = $this->get_doc_meta( $doc_id , $meta_key , $item_id );
		if ( ! $exists )
		{
			if( ! empty ( $meta_value ) ) //DELETE IF EMPTY
			{
				$wpdb->insert(
					$this->_tbl_document_meta,
					array(
						'doc_id' 			=> $doc_id,
						'item_id' 			=> $item_id,
						'meta_key' 			=> $meta_key,
						'meta_value' 		=> $meta_value
					),
					array(
						'%d', '%d', '%s', '%s'
					)
				);
				$meta_id = absint( $wpdb->insert_id ); //V1.0.3
				if( ! $meta_id )
					$succ = false;
			}
		} else {
			if( ! $meta_value || empty ( $meta_value ) ) //DELETE IF EMPTY
			{
				if( ! $this->delete_document_meta( array( "meta_key" => $meta_key , "doc_id" => $doc_id , "item_id" => $item_id ) ) ) //V1.0.3
					return false;
			}
			else //UPDATE IF NOT EMPTY
			{
				$meta_id = $exists['meta_id']; //V1.0.3
				if( ! $this->update_document_meta( $exists['meta_id'] , array( "meta_value" => $meta_value) ) )
				{
					$succ = false;
				}
			}
		}
		//echo "<br />------------------".$exists."--D-".$doc_id."----I-".$item_id."----".$meta_key."-----".$meta_value."----".$item_id."----".$succ;
		return $succ;
	}

	/**
	 *	Data Handle
	 */
	public function parent_data_handle( $action , $header = array() , $details = array() )
	{
		switch ( strtolower( $action ) ){
			case "save":
			case "update":
				//System Generated Document No.
				$header['doc_id'] 		= isset($header['doc_id']) ? sanitize_text_field($header['doc_id']) : '';
				$header['warehouse_id'] = isset($header['warehouse_id']) ? sanitize_text_field($header['warehouse_id']) : '';
				$header['docno'] 		= isset($header['docno']) ? sanitize_text_field($header['docno']) : '';
				$header['doc_date'] 	= isset($header['doc_date']) ? sanitize_text_field($header['doc_date']) : '';

				//Custom Field
				$header['remark'] 		= isset($header['remark']) ? $header['remark'] : '';
				
				$len = count($details);
				for( $i = 0; $i < $len; $i++ ){
					$details[$i]['product_id'] 	= isset($details[$i]['product_id']) ? sanitize_text_field($details[$i]['product_id']) : '';
					$details[$i]['strg_id'] 	= isset($details[$i]['strg_id']) ? sanitize_text_field($details[$i]['strg_id']) : '';
					$details[$i]['uom_id']	 	= isset($details[$i]['uom_id']) ? sanitize_text_field($details[$i]['uom_id']) : '';
					$details[$i]['bqty'] 		= isset($details[$i]['bqty']) ? sanitize_text_field($details[$i]['bqty']) : '';
					$details[$i]['bunit'] 		= isset($details[$i]['bunit']) ? sanitize_text_field($details[$i]['bunit']) : '';
					$details[$i]['ref_doc_id'] 	= isset($details[$i]['ref_doc_id']) ? sanitize_text_field($details[$i]['ref_doc_id']) : '0';
					$details[$i]['ref_item_id'] = isset($details[$i]['ref_item_id']) ? sanitize_text_field($details[$i]['ref_item_id']) : '0';

					//Custom Field
					$details[$i]['dremark'] 	= isset($details[$i]['dremark']) ? sanitize_text_field($details[$i]['dremark']) : '';
					$details[$i]['block'] 		= isset( $details[$i]['block'] ) ? $details[$i]['block'] : 0 ;
				}
			break;
			case "delete":
			case "delete-item":
			case "post":

			break;
		}	
		$succ = $this->child_action_handle( $action , $header , $details );
		return $succ;
	}
	/**
	 *	Add Document Header
	 */
	public function add_document_header( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_document,
			array(
				'docno' 			=> $item['docno'],
				'sdocno' 			=> $item['sdocno'],
				'warehouse_id' 		=> $item['warehouse_id'],
				'doc_type' 			=> $item['doc_type'],
				'doc_date' 			=> $item['doc_date'],
				'post_date'			=> $item['post_date'],
				'status' 			=> $item['status'],
				'flag'				=> $item['flag'],
				'parent'			=> $item['parent'],
				'created_by' 		=> $item['created_by'],
				'created_at' 	    => $item['created_at'],
				'lupdate_by' 		=> $item['lupdate_by'],
				'lupdate_at' 	    => $item['lupdate_at']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Document Header
	 */
	public function update_document_header( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_document, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Add Document Items
	 */
	public function add_document_items( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_document_items,
			array(
				'doc_id' 			=> $item['doc_id'],
				'strg_id'			=> $item['strg_id'],
				'product_id' 		=> $item['product_id'],
				'uom_id' 			=> $item['uom_id'],
				'bqty' 				=> $item['bqty'],
				'bunit' 			=> $item['bunit'],
				'ref_doc_id' 		=> $item['ref_doc_id'],
				'ref_item_id' 		=> $item['ref_item_id'],
				'status' 			=> $item['status'],
				'created_by' 		=> $item['created_by'],
				'created_at' 	    => $item['created_at'],
				'lupdate_by' 		=> $item['lupdate_by'],
				'lupdate_at' 	    => $item['lupdate_at']
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Document Items
	 */
	public function update_document_items( $cond , $item ){
		global $wpdb;

		if ( ! $cond || ! $item ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_document_items, $item, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Delete Document Items
	 */
	public function delete_document_items( $doc_id , $active_items = array() , $excluded = true ){
		global $wpdb;

		if ( ! $doc_id ) {
			return false;
		}
		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_document_items." set status = 0 WHERE doc_id = %d AND status != 0 ", $doc_id );

		if( count($active_items) > 0 ) 
		{
			$upd_exclude = $excluded === false ? " IN " : " NOT IN ";
			$update_items_sql.=" AND item_id ".$upd_exclude." ( " . implode( ',', $active_items ) . ")";
		}
		$update = $wpdb->query( $update_items_sql );
		//echo "<BR /> DELETED ITEM : ".$update_items_sql."<BR />";
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Get Header
	 */
	public function get_header( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$dbname = $this->dbName();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
        {
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $field = "a.* ";
		$table = "{$dbname}{$this->_tbl_document} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$isParent = ( $args && $args['parent'] )? true : false;
		if( $isParent )
		{
			$field.= ", prt.warehouse_id AS prt_warehouse_id, prt.docno AS prt_docno, prt.doc_date AS prt_doc_date, prt.doc_type AS prt_doc_type ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_document} prt ON prt.doc_id = a.parent ";
		}

		$isWarehouse = ( $args && $args['warehouse'] )? true : false;
		$isCompany = ( $args && $args['company'] )? true : false;
		if( $isWarehouse || $isCompany )
		{
			$field.= ", wh.id AS wh_id, wh.code AS wh_code, wh.name AS wh_name ";
			$table.= "LEFT JOIN {$dbname}{$prefix}warehouse wh ON wh.code = a.warehouse_id ";
		}
		
		if( $isCompany )
		{
			$field.= ", comp.id AS comp_id, comp.custno AS comp_custno, comp.code AS comp_code, comp.name AS comp_name ";
			$field.= ", comp.tin, comp.id_type, comp.id_code, comp.sst_no, comp.einv ";
			$table.= "LEFT JOIN {$dbname}{$prefix}company comp ON comp.id = wh.comp_id ";
		}

		
		$isTransactOut = ( $args && $args['transact_out'] )? true : false;
		if( $isTransactOut )
		{
			$field.= ", tr.hid, tr.status AS tr_status ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction} tr ON tr.doc_id = a.doc_id AND tr.doc_type = a.doc_type AND tr.status > 0 ";
			$table.= " AND tr.hid = ( SELECT DISTINCT ref_hid FROM {$dbname}{$this->_tbl_transaction_out} WHERE status > 0 AND bqty > 0 AND ref_hid = tr.hid ) ";
		}

		$isTransact = ( $args && ( $args['transact'] || $args['available_transact'] ) )? true : false;
		if( $isTransact )
		{
			$field.= ", SUM( ti.bqty ) AS stk_bqty, SUM( ti.deduct_qty ) AS stk_uqty ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction} t ON t.doc_id = a.doc_id AND t.status > 0 ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction_items} ti ON ti.hid = t.hid AND ti.status > 0 ";
		}

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['not_doc_id'] ) )
		{
			if( is_array( $filters['not_doc_id'] ) )
				$cond.= "AND a.doc_id NOT IN ('" .implode( "','", $filters['not_doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id != %d ", $filters['not_doc_id'] );
		}
		if( isset( $filters['warehouse_id'] ) )
		{
			if( is_array( $filters['warehouse_id'] ) )
				$cond.= "AND a.warehouse_id IN ('" .implode( "','", $filters['warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['not_warehouse_id'] ) )
		{
			if( is_array( $filters['not_warehouse_id'] ) )
				$cond.= "AND a.warehouse_id NOT IN ('" .implode( "','", $filters['not_warehouse_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.warehouse_id != %s ", $filters['not_warehouse_id'] );
		}
		if( isset( $filters['docno'] ) )
		{
			if( is_array( $filters['docno'] ) )
				$cond.= "AND a.docno IN ('" .implode( "','", $filters['docno'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.docno = %s ", $filters['docno'] );
		}
		if( isset( $filters['sdocno'] ) )
		{
			if( is_array( $filters['sdocno'] ) )
				$cond.= "AND a.sdocno IN ('" .implode( "','", $filters['sdocno'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.sdocno = %s ", $filters['sdocno'] );
		}
		if( isset( $filters['doc_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date = %s ", $filters['doc_date'] );
		}
		if( isset( $filters['doc_date_from'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $filters['doc_date_from'] );
		}
		if( isset( $filters['doc_date_to'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['doc_date_to'] );
		}
		if( isset( $filters['doc_type'] ) )
		{
			if( $filters['doc_type'] != 'none' )
			{
				if( is_array( $filters['doc_type'] ) )
					$cond.= "AND a.doc_type IN ('" .implode( "','", $filters['doc_type'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $filters['doc_type'] );
			}
		}
		else
		{
			if( $this->_doc_type != 'none' )
			{
				if( is_array( $this->_doc_type ) )
					$cond.= "AND a.doc_type IN ('" .implode( "','", $this->_doc_type ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->_doc_type );
			}
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %d ", $filters['parent'] );
		}

		$field.= ", pd.meta_value AS posting_date ";
		$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_meta} pd ON pd.doc_id = a.doc_id AND pd.item_id = 0 AND pd.meta_key = 'posting_date' ";
		
		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->_tbl_document_meta} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = 0 AND {$meta_key}.meta_key = %s ", $meta_key );

				if( $meta_key == 'client_company_code' )
				{
					$table.= "LEFT JOIN {$dbname}{$this->_tbl_client} c ON c.code = {$meta_key}.meta_value ";
					$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->_tbl_client_tree} ";
					$subsql.= "WHERE 1 AND descendant = c.id ORDER BY level DESC LIMIT 0,1 ";
					$table.= "LEFT JOIN {$dbname}{$this->_tbl_client} cc ON cc.id = ( {$subsql} ) ";

					if( isset( $filters[$meta_key] ) )
					{
						if( is_array( $filters[$meta_key] ) )
						{
							$catcd = "c.code IN ('" .implode( "','", $filters[$meta_key] ). "') ";
							$catcd.= "OR cc.code IN ('" .implode( "','", $filters[$meta_key] ). "') ";
							$cond.= "AND ( {$catcd} ) ";
						}
						else
						{
							$catcd = $wpdb->prepare( "c.code = %s ", $filters[$meta_key] );
							$catcd = $wpdb->prepare( "OR cc.code = %s ", $filters[$meta_key] );
							$cond.= "AND ( {$catcd} ) ";
						}
					}
				}
				else
				{
					if( isset( $filters[$meta_key] ) )
					{
						if( is_array( $filters[$meta_key] ) )
							$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
						else
						{
							if( $filters[$meta_key] == 'IS_NULL' )
							{
								$cond.= "AND ( {$meta_key}.meta_value IS NULL OR {$meta_key}.meta_value = '' ) ";
							}
							else if( $filters[$meta_key] == 'IS_NOT_NULL' )
							{
								$cond.= "AND {$meta_key}.meta_value IS NOT NULL AND {$meta_key}.meta_value != '' ";
							}
							else
							{
								$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
							}
						}
					}
				}
			}
		}

		if( $args['dmeta'] )
		{
			foreach( $args['dmeta'] as $meta_key )
			{
				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->_tbl_document_meta} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id > 0 AND {$meta_key}.meta_key = %s ", $meta_key );
				
				if( isset( $filters[$meta_key] ) )
				{
					if( is_array( $filters[$meta_key] ) )
						$cond.= "AND {$meta_key}.meta_value IN ('" .implode( "','", $filters[$meta_key] ). "') ";
					else
						$cond.= $wpdb->prepare( "AND {$meta_key}.meta_value = %s ", $filters[$meta_key] );
				}
			}
		}

		if( ! $args['off_det'] )
		{
			$field.= ", (SELECT SUM(det.bqty) AS t_bqty FROM {$dbname}{$this->_tbl_document_items} det WHERE det.doc_id = a.doc_id AND det.status >= 0 ) AS t_bqty ";
			$field.= ", (SELECT SUM(det.uqty) AS t_uqty FROM {$dbname}{$this->_tbl_document_items} det WHERE det.doc_id = a.doc_id AND det.status >= 0 ) AS t_uqty ";
		}

		if( !empty( $args['child_doc'] ) )
		{
			$c_fld = "group_concat( concat( cdn.doc_id,'-',cdn.docno ) separator ',' ) AS child_info ";
			$c_cond = "";
			if( is_array( $args['child_doc'] ) )
			{
				$c_cond.= "AND cdn.doc_type IN( '".implode( "', '", $args['child_doc'] )."' )";
			}
			else
			{
				$c_cond.= $wpdb->prepare( "AND cdn.doc_type = %s ", $args['child_doc'] );
			}
			
			$field.= ", ( SELECT {$c_fld} FROM {$dbname}{$this->_tbl_document} cdn WHERE cdn.parent = a.doc_id AND cdn.status > 0 {$c_cond}
				GROUP BY cdn.parent ) AS child_info ";
		}

		if( isset( $filters['product_id'] ) )
        {
        	$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_items} det ON det.doc_id = a.doc_id AND det.status >= 0 ";

            if( is_array( $filters['product_id'] ) )
				$cond.= "AND det.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND det.product_id = %d ", $filters['product_id'] );

			$group[] = "a.doc_id";
        }

        if( $args['doc_date_lesser'] )
        {
        	$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $args['doc_date_lesser'] );
        }
        if( $args['doc_date_greater'] )
        {
        	$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $args['doc_date_greater'] );
        }

        if( isset( $filters['s'] ) )
        {
        	$search = explode( ',', trim( $filters['s'] ) );	
        	$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );
        	
        	$cond.= "AND ( ";

        	$seg = array();
        	foreach( $search as $kw )
        	{
        		$kw = trim( $kw );
	            $cd = array();
	            $cd[] = "a.docno LIKE '%".$kw."%' ";
	            $cd[] = "a.sdocno LIKE '%".$kw."%' ";

	            if( $args['meta'] )
				{
					foreach( $args['meta'] as $meta_key )
					{
						$cd[] = "{$meta_key}.meta_value LIKE '%".$kw."%' ";
					}
				}

				if( $args['dmeta'] )
				{
					foreach( $args['dmeta'] as $meta_key )
					{
						$cd[] = "{$meta_key}.meta_value LIKE '%".$kw."%' ";
					}
				}

	            $seg[] = "( ".implode( "OR ", $cd ).") ";
        	}
        	$cond.= implode( "OR ", $seg );

        	$cond.= ") ";

            unset( $filters['status'] );
			
			if($filters['CDNote_Status']) //PO Credit/Debit Note Used
			{
				$filters['status'] = $filters['CDNote_Status'];//'process';//wid001 modified v2.8.1.3
			}
        }

		$corder = array();
        //status
		//if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) ) //original version
		if( ! isset( $filters['status'] ) || ( !is_array($filters['status']) && isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) ) //mha004 modified v2.8.3.23
        {
            unset( $filters['status'] );
			$cond.= $wpdb->prepare( "AND a.status > %d ", -1 );

            $field.= ", (SELECT stat.order FROM {$dbname}{$this->_tbl_status} stat WHERE stat.status = a.status AND stat.type = 'default') AS stat_idx ";
            //$table.= "LEFT JOIN {$dbname}{$this->_tbl_status} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat_idx"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
        	if( $filters['status'] == 'process' && $this->processing_stat )
        	{
        		$cond.= "AND ( a.status IN( ".implode( ', ', $this->processing_stat )." ) ";

        		$field.= ", (SELECT stat.order FROM {$dbname}{$this->_tbl_status} stat WHERE stat.status = a.status AND stat.type = 'default') AS stat_idx ";
        		//$table.= "LEFT JOIN {$dbname}{$this->_tbl_status} stat ON stat.status = a.status AND stat.type = 'default' ";
            	$corder["stat_idx"] = "DESC";

            	if( !empty( $args['recent'] ) )
            	{
            		$cond.= $wpdb->prepare( "OR a.created_at >= %s ", date( 'Y-m-d', strtotime( current_time( 'mysql' )." -{$args['recent']} day" ) ) );
            	}

            	$cond.= ") ";
        	}
        	else
			{
				if( is_array( $filters['status'] ) )
					$cond.= "AND a.status IN ('" .implode( "','", $filters['status'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
				////wid001 modified v2.8.1.3
			}
        }
        //flag
        if( isset( $filters['flag'] ) && $filters['flag'] != "" )
        {   
            $cond.= $wpdb->prepare( "AND a.flag = %s ", $filters['flag'] );
        }
        if( $this->useFlag )
        {
        	$field.= ", (SELECT flag.order FROM {$dbname}{$this->_tbl_status} flag WHERE flag.status = a.flag AND flag.type = 'flag') AS flag_idx ";
            //$table.= "LEFT JOIN {$dbname}{$this->_tbl_status} flag ON flag.status = a.flag AND flag.type = 'flag' ";
            $corder["flag_idx"] = "DESC";
        }

		$isUse = ( $args && isset( $args['usage'] ) )? true : false;
		$isPost = ( $args && $args['posting'] )? true : false;
		if( $isUse || $isPost )
		{
			$cond.= $wpdb->prepare( "AND a.status >= %d AND a.flag = %d ", $args['usage'], 1 );

			if( $isPost )
			{
				$cond.= $wpdb->prepare( "AND a.status >= %d ", 6 );
			}
		}

		//group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date' => 'DESC', 'a.doc_id' => 'DESC' ];
        	$order = array_merge( $corder, $order );
		} 

        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

        //limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
	
		return $results;
	}
	/**
	 *	Get Detail
	 */
	public function get_detail( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$dbname = $this->dbName();
		
		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
        {
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $field = "a.* ";
		$table = "{$dbname}{$this->_tbl_document_items} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";
		
		$isItem = ( $args && $args['item'] )? true : false;
		$isUom = ( $args && $args['uom'] )? true : false;
		$isCategory = ( $args && $args['category'] )? true : false;
		$isReturnable = ( $args && $args['returnable'] )? true : false;
		if( $isItem || $isUom || $isCategory || $isReturnable)
		{
			$field.= ", prdt.name AS prdt_name, prdt._sku AS sku, prdt.code AS prdt_code, prdt.serial AS prdt_serial, prdt._uom_code AS uom, prdt._self_unit AS self_unit, prdt._content_uom AS content_uom, prdt._parent_unit AS parent_unit, prdt.parent ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product} prdt ON prdt.id = a.product_id ";

			$field.= ", meta_a.meta_value AS inconsistent_unit ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product_meta} meta_a ON meta_a.items_id = prdt.id AND meta_a.meta_key = 'inconsistent_unit' ";

			$field.= ", meta_b.meta_value AS spec ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product_meta} meta_b ON meta_b.items_id = prdt.id AND meta_b.meta_key = 'spec' ";
			
			if( $this->refs['metric'] )
			{
				foreach( $this->refs['metric'] AS $each )
				{
					$each = strtoupper($each);
					$met[] = "UPPER( prdt._uom_code ) = '{$each}' ";
				}

				$metric = "AND NOT ( ".implode( "OR ", $met ).") ";
			}

			$field.= ", IF( rep.id > 0 AND meta_a.meta_value > 0 {$metric}, 1, 0 ) AS required_unit ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_reprocess_item} rep ON rep.items_id = a.product_id AND rep.status > 0 ";

			$group = array_merge( $group, [ 'a.item_id' ] );
		}
		if( $isUom )
		{
			$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_uom} uom ON uom.code = prdt._uom_code ";
		}
		if( $isCategory )
		{
			$field.= ", cat.name AS cat_name, cat.slug AS cat_code ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_category} cat ON cat.term_id = prdt.category ";
		}
		$isGrp = ( $args && $args['group'] )? true : false;
		if( $isGrp )
		{
			$field.= ", grp.name AS grp_name, grp.code AS grp_code ";
			$table.= " LEFT JOIN {$dbname}{$this->_tbl_item_group} grp ON grp.id = prdt.grp_id ";
		}
		if( $isReturnable )
		{
			$field.= ", meta_c.meta_value AS returnable_item ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_product_meta} meta_c ON meta_c.items_id = prdt.id AND meta_c.meta_key = 'returnable_item' ";
		}
		
		$isRefTransact = ( $args && $args['ref_transact'] )? true : false;
		$isRef = ( $args && $args['ref'] )? true : false;
		if( $isRef || $isRefTransact )
		{
			$field.= ", ref.bqty AS ref_bqty, ref.uqty AS ref_uqty, ref.bunit AS ref_bunit, ref.uunit AS ref_uunit ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_items} ref ON ref.doc_id = a.ref_doc_id AND ref.item_id = a.ref_item_id ";

			if( $isRefTransact )
			{
				$field.= ", ritran.product_id AS ref_tran_prdt_id, ritran.bqty AS ref_tran_bqty, ritran.bunit AS ref_tran_bunit
					, ritran.unit_cost AS ref_unit_cost, ritran.total_cost AS ref_total_cost
					, ritran.unit_price AS ref_unit_price, ritran.total_price AS ref_total_price, ritran.plus_sign AS ref_plus_sign
					, ritran.weighted_price AS ref_weighted_price, ritran.weighted_total AS ref_weighted_total ";
				$field.= ", ritran.deduct_qty AS ref_deduct_qty, ritran.deduct_unit AS ref_deduct_unit, ritran.status AS ref_tran_status, ritran.flag AS ref_tran_flag ";
				$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction_items} ritran ON ritran.item_id = ref.item_id AND ritran.status != 0 ";
			}
		}

		$isTransact = ( $args && $args['transact'] )? true : false;
		if( $isTransact )
		{
			$field.= ", itran.product_id AS tran_prdt_id, itran.bqty AS tran_bqty, itran.bunit AS tran_bunit, itran.unit_cost, itran.total_cost, itran.unit_price, itran.total_price, itran.plus_sign, itran.weighted_price, itran.weighted_total ";
			$field.= ", itran.deduct_qty, itran.deduct_unit, itran.status AS tran_status, itran.flag AS tran_flag ";
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_transaction_items} itran ON itran.item_id = a.item_id AND itran.status != 0 ";
		}

		$isStocks = ( $args && $args['stocks'] )? true : false;
		if( $isStocks )
		{
			$field.= ", inv.qty AS stock_qty, inv.allocated_qty AS stock_allocated ";
			
			if( $isItem || $isUom || $isCategory || $isReturnable )
			{
				$table.= $wpdb->prepare( " LEFT JOIN {$dbname}{$this->_tbl_inventory} inv ON inv.prdt_id = IF( prdt.ref_prdt > 0, prdt.ref_prdt, a.product_id ) 
					AND inv.warehouse_id = %s ", $args['stocks'] );
			}
			else
			{
				$table.= $wpdb->prepare( " LEFT JOIN {$dbname}{$this->_tbl_inventory} inv ON inv.prdt_id = a.product_id AND inv.warehouse_id = %s ", $args['stocks'] );
			}
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_storage} strg ON strg.id = inv.strg_id ";
			$cond.= $wpdb->prepare( "AND ( strg.sys_reserved = %s OR inv.id IS NULL ) ", 'staging' );
		}

		if( isset( $filters['doc_id'] ) )
		{
			if( is_array( $filters['doc_id'] ) )
				$cond.= "AND a.doc_id IN ('" .implode( "','", $filters['doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id = %d ", $filters['doc_id'] );
		}
		if( isset( $filters['not_doc_id'] ) )
		{
			if( is_array( $filters['not_doc_id'] ) )
				$cond.= "AND a.doc_id NOT IN ('" .implode( "','", $filters['not_doc_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.doc_id != %d ", $filters['not_doc_id'] );
		}
		if( isset( $filters['item_id'] ) )
		{
			if( is_array( $filters['item_id'] ) )
				$cond.= "AND a.item_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.item_id = %d ", $filters['item_id'] );
		}
		if( isset( $filters['strg_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.strg_id = %d ", $filters['strg_id'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND a.product_id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.product_id = %d ", $filters['product_id'] );
		}
		if( isset( $filters['uom_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.uom_id = %d ", $filters['uom_id'] );
		}
		if( isset( $filters['ref_doc_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_doc_id = %d ", $filters['ref_doc_id'] );
		}
		if( isset( $filters['ref_item_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_item_id = %d ", $filters['ref_item_id'] );
		}
		
		if( $args['meta'] )
		{
			foreach( $args['meta'] as $meta_key )
			{
				if( $meta_key == '_item_key' ) continue;

				$field.= ", {$meta_key}.meta_value AS {$meta_key} ";
				$table.= $wpdb->prepare( "LEFT JOIN {$dbname}{$this->_tbl_document_meta} {$meta_key} ON {$meta_key}.doc_id = a.doc_id AND {$meta_key}.item_id = a.item_id AND {$meta_key}.meta_key = %s ", $meta_key );
			}
		}

		$field.= ", CAST( idx.meta_value AS UNSIGNED ) AS idx ";
		$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_meta} idx ON idx.doc_id = a.doc_id AND idx.item_id = a.item_id AND idx.meta_key = '_item_number' ";

		$corder = array();
        //status
		if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
		{
			unset( $filters['status'] );
		}
		else
		{
			$cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
		}

		$isUse = ( $args && $args['usage'] )? true : false;
		$isPost = ( $args && $args['posting'] )? true : false;
		if( $isUse || $isPost )
		{
			$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );

			if( $isPost )
			{
				$cond.= $wpdb->prepare( "AND a.status == %d ", 6 );
			}
		}

		//group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

		//order
        $order = !empty( $order )? $order : [ 'idx' => 'ASC', 'a.item_id' => 'ASC' ];
        $order = array_merge( $corder, $order );
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

        //limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}
	/**
	 * 
	 */
	public function get_child_doc_ids( $doc_id = 0, $filters = [] )
	{
		if( ! $doc_id ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$dbname = $this->dbName();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
        {
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

		$field = "DISTINCT a.doc_id, a.warehouse_id, a.doc_type, a.docno, a.doc_date, a.status ";
		$table = "{$dbname}{$this->_tbl_document} a ";
		$table.= "LEFT JOIN {$dbname}{$this->_tbl_document} b ON b.warehouse_id = a.warehouse_id ";
		$cond = "AND a.status > 0 ";

		if( $filters['ref_doc_id'] )
		{
			$table.= "LEFT JOIN {$dbname}{$this->_tbl_document_meta} a1 ON a1.doc_id = a.doc_id AND a1.item_id = 0 AND a1.meta_key = 'ref_doc_id' ";
			$cond.= $wpdb->prepare( "AND ( a.parent = %s OR a1.meta_value = %s ) ", $doc_id, $doc_id );
		}
		else
		{
			$cond.= $wpdb->prepare( "AND a.parent = %s ", $doc_id );
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond}  ORDER BY doc_id ASC ";

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results;
	}
	/**
	 *	Get Document Header V1.0.3 Add optional status
	 */
	public function get_document_header( $doc_id , $status = '', $flag = '' ){
		global $wpdb;

		if ( ! $doc_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document." WHERE doc_id = %d ", $doc_id );

		$fld = "h.*, ma.meta_value AS posting_date ";
		$tbl = "{$this->_tbl_document} h ";
		$tbl.= "LEFT JOIN {$this->_tbl_document_meta} ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'posting_date' ";
		$cond = $wpdb->prepare( "AND h.doc_id = %s ", $doc_id );

		if( $status != '' )
		{
			$cond .= $wpdb->prepare( " AND status = %s ", $status );
		}
		else
		{
			$cond .= $wpdb->prepare( " AND status != %s ", '0' );
		}
		if( $flag != '' )
		{
			$cond .= $wpdb->prepare( " AND flag = %s ", $flag );
		}

		$get_items_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Document Items By Document ID V1.0.1
	 */
	public function get_document_items_by_doc( $doc_id , $status = 'active' ){
		global $wpdb;

		if ( ! $doc_id ) {
			return false;
		}
		$get_items_sql = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_items." WHERE doc_id = %d ", $doc_id );

		if( $status == 'active' )
			$get_items_sql .= " AND status != 0 ";
		else if ( $status >= 0 )
			$get_items_sql .= " AND status = ".$status;

		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Document Items
	 */
	public function get_document_items( $item_id  ){
		global $wpdb;

		if ( ! $item_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_items." WHERE item_id = %d AND status != 0 ", $item_id );

		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Existing Document Items
	 */
	public function get_exists_document_items( $doc_id, $product_id , $ref_doc_id = 0, $ref_item_id = 0 , $block = 0 ){
		global $wpdb;

		if ( ! $doc_id  || ! $product_id ) {
			return false;
		}
		if( isset( $block ) && $block > 0 )
		{
			$table = " LEFT JOIN ".$this->_tbl_document_items." b ON b.doc_id = a.doc_id AND b.item_id = a.item_id AND b.meta_key = 'block' ";
			$fld = ", b.meta_value as block";
			$cond = " AND b.meta_value = '".$block."'";
		}
		else
		{
			$table = "";
			$fld = ", 0 as block";	
			$cond = "";
		}
		$get_items_sql  = $wpdb->prepare( "SELECT a.* ".$fld." FROM ".$this->_tbl_document_items." a ".$table." WHERE a.doc_id = %d AND a.product_id = %d AND a.status != 0 ".$cond, $doc_id ,$product_id );

		if( $ref_item_id > 0 )
		{
			$get_items_sql .= $wpdb->prepare( " AND a.ref_doc_id = %d AND a.ref_item_id = %d " , $ref_doc_id , $ref_item_id );
		}
		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Existing Document Items by id(s) V1.0.1
	 */
	public function get_exists_document_items_by_item_id( $item_id_arr = array() ){
		global $wpdb;
		
		$item_id_arr = array_filter( $item_id_arr );

		if ( ! $item_id_arr  || count( $item_id_arr ) == 0 ) {
			return false;
		}
		$get_items_sql  = "SELECT * FROM ".$this->_tbl_document_items." WHERE status != 0 ";

		if( count($item_id_arr) > 0 ) 
		{
			$get_items_sql.=" AND item_id IN ( " . implode( ',', $item_id_arr ) . ")";
		}
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Check IF Exists Document Items Before Deletion V1.0.1
	 */
	public function get_deletion_document_items( $doc_id , $active_items = array() ){
		global $wpdb;

		if ( ! $doc_id  ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_items." WHERE doc_id = %d AND status != %s ", $doc_id, '0' );

		if( count($active_items) > 0 ) 
		{
			$get_items_sql.=" AND item_id NOT IN ( " . implode( ',', $active_items ) . ")";
		}
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Add Document Meta
	 */
	public function add_document_meta( $item ){
		global $wpdb;
		$wpdb->insert(
			$this->_tbl_document_meta,
			array(
				'doc_id' 			=> $item['doc_id'],
				'item_id' 			=> $item['item_id'],
				'meta_key' 			=> $item['meta_key'],
				'meta_value' 		=> $item['meta_value']
			),
			array(
				'%d', '%d', '%s', '%s'
			)
		);
		$item_id = absint( $wpdb->insert_id );
		return $item_id;
	}
	/**
	 *	Update Document Meta
	 */
	public function update_document_meta( $meta_id , $item ){
		global $wpdb;

		if ( ! $meta_id ) {
			return false;
		}
		$update = $wpdb->update( $this->_tbl_document_meta, $item, array( 'meta_id' => $meta_id ) );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Delete Document Meta
	 */
	public function delete_document_meta( $cond ){
		global $wpdb;

		if ( ! $cond ) {
			return false;
		}
		$update = $wpdb->delete( $this->_tbl_document_meta, $cond );

		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Get Document Meta
	 */
	public function get_document_meta_by_id( $meta_id ){
		global $wpdb;

		if ( ! $meta_id ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_meta." WHERE meta_id = %d ", $meta_id );

		return $wpdb->get_row( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Document Meta
	 */
	public function get_doc_meta( $doc_id , $meta_key, $item_id = 0, $val_only = false ){
		global $wpdb;

		if ( ! $doc_id || empty ( $meta_key ) ) {
			return false;
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_meta." WHERE doc_id = %d AND item_id = %d AND meta_key = %s", $doc_id , $item_id , $meta_key );
		$row = $wpdb->get_row( $get_items_sql , ARRAY_A );
		
		return ( $val_only )? $row['meta_value'] : $row;
	}
	public function get_document_meta( $doc_id = 0, $meta_key = '', $item_id = 0, $single = false )
	{
		if( ! $doc_id ) return false;
		
		global $wpdb;
		$dbname = $this->dbName();
		
		$cond = $wpdb->prepare( " AND doc_id = %d", $doc_id );
		if( !empty( $meta_key ) ) $cond.= $wpdb->prepare( " AND meta_key = %s", $meta_key );
		$cond.= $wpdb->prepare( " AND item_id = %d", $item_id );
		
		$sql  = "SELECT * FROM {$dbname}{$wpdb->doc_meta} WHERE 1 {$cond} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$metas = array();
		if( $results )
		{
			foreach( $results as $row )
			{
				if( !empty( $meta_key ) )
				{
					$metas[] = $row['meta_value'];
				}
				else
				{
					$metas[$row['meta_key']][] = $row['meta_value'];
				}
			}
		}
		
		if( !empty( $meta_key ) && $single )
		{
			return $metas[0];
		}

		return $metas;
	}
	/**
	 *	Get Document Metas
	 */
	public function get_document_metas( $doc_id , $meta_key, $item_id = 0 ){
		global $wpdb;

		if ( ! $doc_id || empty ( $meta_key ) ) {
			return false;
		}
		
		if( $item_id ){
			$cond = $wpdb->prepare( " AND item_id = %d", $item_id );
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_meta." WHERE doc_id = %d AND meta_key = %s".$cond, $doc_id , $meta_key );

		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Find Document Meta
	 */
	public function find_document_metas( $doc_id , $meta_key, $item_id = 0 ){
		global $wpdb;

		if ( ! $doc_id || empty ( $meta_key ) ) {
			return false;
		}
		
		if( $item_id ){
			$cond = $wpdb->prepare( " AND item_id = %d", $item_id );
		}
		$get_items_sql  = $wpdb->prepare( "SELECT * FROM ".$this->_tbl_document_meta." WHERE doc_id = %d AND meta_key LIKE '%{$meta_key}%' ".$cond, $doc_id );

		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Outstanding Item 
	 *  V1.0.1 Outstanding replace left join to Inner Join
	 */
	public function get_outstanding_item( $doc_type , $warehouse = '', $storage = 0, $product = 0, $doc_id = 0 , $customer = 0 )
	{
		if( ! $doc_type )
		{
			return false;
		}
		global $wpdb;
		$fld = " a.doc_id, a.docno, a.doc_date, b.item_id, b.strg_id, b.product_id,b.uom_id, b.bqty,b.uqty , b.bqty - b.uqty as osqty, b.bunit,b.uunit , b.bunit - b.uunit as osunit "; 
		$table = $this->_tbl_document." a ";
		$table .= " INNER JOIN " .$this->_tbl_document_items. " b ON b.doc_id = a.doc_id AND b.status != 0 AND b.bqty > b.uqty ";
		$cond = " WHERE a.status != 0 AND a.doc_type = '".$doc_type."' ";
		$ord = " ";

		if( !empty( $warehouse ) )
		{
			$cond .= $wpdb->prepare(" AND a.warehouse_id = %s ", $warehouse );
		}
		if( $storage > 0 )
		{
			$cond .= $wpdb->prepare(" AND b.strg_id = %d ", $storage );
		}
		if( $product > 0 )
		{
			$cond .= $wpdb->prepare(" AND b.product_id = %d ", $product );
		}
		if( $doc_id > 0 )
		{
			$cond .= $wpdb->prepare(" AND b.doc_id = %d ", $doc_id );
		}
		if( $customer > 0 )
		{
			$table .= " LEFT JOIN " .$this->_tbl_document_meta. " cust ON cust.doc_id = a.doc_id AND cust.item_id = 0 AND cust.meta_key = 'customer' ";
			$cond .= $wpdb->prepare(" AND cust.meta_key = %d ", $customer );
		}


		$get_items_sql = "SELECT ".$fld." FROM ".$table.$cond.$ord;
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 * Get Document with Reference ID (Linked Doocument ) V1.0.1
	 * Note: May overrided this function on child class for separate information needed
	 * KIV - FORCE INDEX(doc_type) 
	 */
	public function get_document_item_with_ref( $doc_type , $ref_doc_id , $ref_item_id = 0 )
	{
		if( ! $doc_type )
		{
			return false;
		}
		global $wpdb;
		$fld = " a.doc_id, a.docno, a.doc_date, b.item_id, b.strg_id, b.product_id, b.uom_id, b.bqty,b.uqty , b.bqty - b.uqty as osqty, b.bunit,b.uunit , b.bunit - b.uunit as osunit "; 
		$fld .= " ,c.name as product_title"; 
		$table = $this->_tbl_document." a FORCE INDEX(doc_type) ";
		$table .= " INNER JOIN " .$this->_tbl_document_items. " b ON b.doc_id = a.doc_id AND b.status != 0 ";
		$table .= " INNER JOIN " .$this->_tbl_product. " c ON c.id = b.product_id ";
		$cond = " WHERE a.status != 0 AND a.doc_type = '".$doc_type."' ";
		$ord = " ";

		if( $ref_doc_id > 0 )
		{
			$cond .= $wpdb->prepare(" AND b.ref_doc_id = %d ", $ref_doc_id );
		}
		if( $ref_item_id > 0 )
		{
			$cond .= $wpdb->prepare(" AND b.ref_item_id = %d ", $ref_item_id );
		}

		$get_items_sql = "SELECT ".$fld." FROM ".$table.$cond.$ord;

		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Return Action Success or Failure
	 */
	public function return_action_respond()
	{
		return $this->succ;
	}
	/**
	 *	Accounting period handle  V1.0.3
	 */
	public function document_account_period_handle( $doc_id = 0 , $date = "", $wh = "", $action = "save" )
	{
		$valid = $this->valid_document_period_control( true, $this->_doc_type, $action );
		if( $valid === true )
		{
			if( $doc_id <= 0 && ( $wh == "" ) )	//check for doc saving
			{
				return false;
			}

			//check exists for status related changes
			$exists = [];
			if( isset( $doc_id ) && $doc_id > 0 )
			{	
				$exists = $this->get_document_header( $doc_id );
				if( $exists )
				{
					$exist_date = $exists['post_date'];
					if( $exists['posting_date'] ) $exist_date = $exists['posting_date'];

					if( isset( $exist_date ) && ! empty( (int)$exist_date ) )
					{
						$date = ( $date )? $date : $exist_date;
						$wh = ( $wh )? $wh : $exists['warehouse_id'];

						if( ! in_array( $action, [ 'save', 'save-post', 'update', 'update-item' ] ) )
						{
							$date = $exist_date;
							$wh = $exists['warehouse_id'];
						}
					}
				}
			}
			
			//Check IF Document Date in Accounting Period 
			if( ! empty( $date ) && ! empty( $wh ) )
			{
				if ( ! $this->check_document_account_period( $date, $wh ) )
				{
					return false; 
				}	
			}

			//Check IF Document Date in Stock Take Control Period 
			if( ! empty( $date ) && ! empty( $wh ) )
			{
				if ( ! $this->check_document_stocktakectrl_period( $date, $wh ) )
				{
					return false; 
				}	
			}
		}

		return true;
	}
	/**
	 *	Check Document Validity for Accounting Period 
	 */
	public function valid_document_period_control( $valid = true, $doc_type = '', $action = '' )
	{
		if( ! $doc_type ) return $valid;

		if( in_array( $doc_type, $this->_doc_exclude_period ) ) $valid = false;

		if( in_array( $action, $this->_action_exclude_period ) ) $valid = false;

		return apply_filters( 'valid_document_period_control', $valid, $doc_type, $action );
	}
	/**
	 *	Check Accounting period
	 */
	public function check_document_account_period( $doc_date, $wh )
	{
		if( ! $doc_date || ! $wh ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$valid = true;

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.warehouse_id = %s ", 'account_period', $wh );
		$cond.= $wpdb->prepare( "AND a.status >= %d AND a.flag > %d ", 3, 0 );
		$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $doc_date );
		$ord = "ORDER BY a.doc_date ASC LIMIT 0,1 ";
		$sql = "SELECT a.* FROM {$this->_tbl_document} a WHERE 1 {$cond} {$ord} ";

		$period = $wpdb->get_row( $sql , ARRAY_A );
		if( $period && $period['status'] == 10 )
		{
			$valid = false;
		}
		
		return apply_filters( 'valid_account_period_filter', $valid, $doc_date, $wh );	
	}
	/**
	 *	Check StockTake Control period
	 */
	public function check_document_stocktakectrl_period( $doc_date, $wh )
	{
		if( ! $doc_date || ! $wh ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$valid = true;

		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.warehouse_id = %s ", 'stocktake_close', $wh );
		$cond.= $wpdb->prepare( "AND a.status >= %d AND a.flag > %d ", 3, 0 );
		$cond.= $wpdb->prepare( "AND a.doc_date >= %s ", $doc_date );
		$ord = "ORDER BY a.doc_date ASC LIMIT 0,1 ";
		$sql = "SELECT a.* FROM {$this->_tbl_document} a WHERE 1 {$cond} {$ord} ";

		$period = $wpdb->get_row( $sql , ARRAY_A );
		if( $period && $period['status'] == 10 )
		{
			$valid = false;
		}
		
		return apply_filters( 'valid_stocktake_close_filter', $valid, $doc_date, $wh );	
	}
	#-----------------------------------------------------------------#
	#	>	M to M UPDATES V1.0.1
	#-----------------------------------------------------------------#	
	/**
	 *	Get Distinct Active Document Item Status
	 */
	public function get_distinct_document_item_status( $doc_id_arr = array() ){
		global $wpdb;

		if ( ! $doc_id_arr || count( $doc_id_arr ) == 0 ) {
			return false;
		}
		$get_items_sql = "SELECT COUNT(DISTINCT status) as sta_cnt, GROUP_CONCAT(DISTINCT status) as status, doc_id FROM ".$this->_tbl_document_items." WHERE status != 0 AND product_id > 0 ";

		if( count($doc_id_arr) > 0 ) 
		{
			$get_items_sql.=" AND doc_id IN ( " . implode( ',', $doc_id_arr ) . ")";
		}
		$get_items_sql.=" GROUP BY doc_id ";

		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Get Incorrect uqty updated: uqty > bqty OR uqty < 0;
	 */
	public function get_incorrect_uqty_updates( $doc_id_arr = array() ){
		global $wpdb;

		if ( ! $doc_id_arr || count( $doc_id_arr ) == 0 ) {
			return false;
		}
		$get_items_sql = "SELECT doc_id,item_id,bqty,uqty,bunit,uunit FROM ".$this->_tbl_document_items." WHERE status != 0 AND ( uqty > bqty || uqty < 0 )";

		if( count($doc_id_arr) > 0 ) 
		{
			$get_items_sql.=" AND doc_id IN ( " . implode( ',', $doc_id_arr ) . ")";
		}
		return $wpdb->get_results( $get_items_sql , ARRAY_A );
	}
	/**
	 *	Update Used Qty, Status
	 *	$sta : full=> bqty = uqty, partial => uqty > 0, empty => uqty = 0;
	 */
	public function update_document_items_uqty( $item_id, $uqty, $uunit = 0 , $plus = "+", $sta = array() ){
		global $wpdb;

		if ( ! $item_id ) {
			return false;
		}
		$upd_status = "";
		if( count($sta) > 0 )
		{
			//$upd_status = "status = IF( uqty ".$plus.$uqty." = bqty, ".$sta['full'].", IF( uqty ".$plus.$uqty." = 0 , ".$sta['empty'].",".$sta['partial'].") ), ";
			$upd_status = ",status = IF( uqty >= bqty && bqty > 0 , ".$sta['full']." , IF( uqty = 0 , ".$sta['empty'].",".$sta['partial'].") ) ";
		}
		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_document_items." set uqty = uqty ".$plus." %s, uunit = uunit ".$plus." %s ".$upd_status." WHERE item_id = %d AND status != 0 ", $uqty, $uunit, $item_id );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />||||".$update_items_sql."----";
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 * Update Document Item Status 
	 * */
	public function update_document_item_status( $item_id, $status )
	{
		global $wpdb;

		if( ! $item_id || ! isset( $status ) ) return false;

		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_document_items." set status = %s WHERE item_id = %d AND status != 0 ", $status, $item_id );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />||||".$update_items_sql."----";
		if ( false === $update ) {
			return false;
		}
		return true;
	} 
	/**
	 *	Update Custom Field
	 */
	public function update_document_items_custom( $item_id, $cond = "", $upd_field = "" ){
		global $wpdb;

		if ( ! $item_id || empty( $upd_field ) ) {
			return false;
		}
		$update_items_sql = $wpdb->prepare( "UPDATE ".$this->_tbl_document_items." set ".$upd_field." WHERE item_id = %d AND status != 0 ".$cond, $item_id );
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />||||".$update_items_sql."----";
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 *	Update Document Status after update item uqty , status 
	 */
	public function update_document_status( $doc_id_arr = array() , $sta ){
		global $wpdb;
		if ( ! $doc_id_arr || count( $doc_id_arr ) == 0 || ! isset( $sta ) ) {
			return false;
		}
		$update_items_sql = "UPDATE ".$this->_tbl_document." set status = '".$sta."' WHERE status != 0 AND status !='".$sta."'";

		if( count($doc_id_arr) > 0 ) 
		{
			$update_items_sql.=" AND doc_id IN ( " . implode( ',', $doc_id_arr ) . ")";
		}
		$update = $wpdb->query( $update_items_sql );
		//echo "<br />||||||---".$update_items_sql."----<br />";
		if ( false === $update ) {
			return false;
		}
		return true;
	}
	/**
	 * Action for Update Linked Document header status
	 * Update Document Status after update item uqty , status 
	 */
	public function update_document_header_status_handles( $doc = array() )
	{
		if ( ! $doc || count( $doc ) == 0 ) 
			return false;
		$sta_doc_arr = array();

		$item_status = $this->get_distinct_document_item_status( $doc );
		if( $item_status ) // V1.0.3
		{
			//Get Document Status
			foreach( $item_status as $item )
			{
				$sta_array = explode( "," , $item['status'] );
				if( count( $sta_array ) == 1 )
				{
					//Update Document Status = Item Status
					$sta_doc_arr[ $sta_array[0] ][] = $item['doc_id'];
				}
				else 
				{
					//More than 1 status = Partial Status
					$sta_doc_arr[ $this->parent_status['partial'] ][] = $item['doc_id'];
				}
			}
		}
		//Update Document Status = Item Status
		if( count( $sta_doc_arr ) > 0 )
		{
			foreach( $sta_doc_arr as $status => $arr_doc_id )
			{
				if(! $this->update_document_status( $arr_doc_id , $status ) )
					$succ = false; 
				else{
					$arr_doc_id = ( is_array( $arr_doc_id ) )? $arr_doc_id[0] : $arr_doc_id;
					if( ! $this->add_document_meta_value( 'status_'.$status.'_date', current_time( 'mysql' ), $arr_doc_id ) )
						$succ = false;
				}
			}
		}
		return true;
	}
	/**
	 *	Action for Update Document Items : Updated Uqty for linked document
	 */
	public function update_items_uqty_handles( $exist_item , $update_item )
	{
		if( ! $this->_upd_uqty_flag ) // V1.0.7 skip update uqty
			return true;
		if( ! $update_item )
			return false;
		//No Changes on uqty
		if( $exist_item['ref_item_id'] == $update_item['ref_item_id'] && $exist_item['bqty'] == $update_item['bqty'] )
			return true;

		//User Change Link Document
		if( ! $exist_item && isset( $update_item['ref_item_id'] ) && $update_item['ref_item_id'] != "0" ) //new added V1.0.2
		{
			$ref_row = $this->get_detail( [ 'item_id'=>$update_item['ref_item_id'], 'doc_id'=>$update_item['ref_doc_id'] ], [], true );
			if( $ref_row && $ref_row['product_id'] != $update_item['product_id'] )
			{
				$update_item['bqty'] = apply_filters( 'wcwh_item_uom_conversion', $update_item['product_id'], $update_item['bqty'], $ref_row['product_id'] );
			}

			//Set new uqty for new Document.
			if ( ! $this->update_document_items_uqty( $update_item['ref_item_id'] , $update_item['bqty'], $update_item['bunit'] , "+", $this->parent_status ) )
				return false;
		}
		else if( $exist_item['ref_item_id'] != $update_item['ref_item_id'] ) //new added V1.0.2
		{
			//Unset Previous Document, Offset Qty V1.0.2
			$ref_row = $this->get_detail( [ 'item_id'=>$exist_item['ref_item_id'], 'doc_id'=>$exist_item['ref_doc_id'] ], [], true );
			if( $ref_row && $ref_row['product_id'] != $exist_item['product_id'] )
			{
				$exist_item['bqty'] = apply_filters( 'wcwh_item_uom_conversion', $exist_item['product_id'], $exist_item['bqty'], $ref_row['product_id'] );
			}
			if ( isset( $exist_item['ref_item_id']) && $exist_item['ref_item_id'] != "0" )
				if(  ! $this->update_document_items_uqty( $exist_item['ref_item_id'] , $exist_item['bqty'], $exist_item['bunit'] , "-", $this->parent_status ) )
					return false;

			//Set new uqty for new Document. V1.0.2
			$ref_row = $this->get_detail( [ 'item_id'=>$update_item['ref_item_id'], 'doc_id'=>$update_item['ref_doc_id'] ], [], true );
			if( $ref_row && $ref_row['product_id'] != $update_item['product_id'] )
			{
				$update_item['bqty'] = apply_filters( 'wcwh_item_uom_conversion', $update_item['product_id'], $update_item['bqty'], $ref_row['product_id'] );
			}
			if ( $update_item['ref_item_id'] != "0" && ! $this->update_document_items_uqty( $update_item['ref_item_id'] , $update_item['bqty'], $update_item['bunit'] , "+", $this->parent_status ) )
				return false;

		}
		else if( $exist_item['product_id'] != $update_item['product_id'] )
		{
			//Unset Previous Document, Offset Qty
			$ref_row = $this->get_detail( [ 'item_id'=>$exist_item['ref_item_id'], 'doc_id'=>$exist_item['ref_doc_id'] ], [], true );
			if( $ref_row && $ref_row['product_id'] != $exist_item['product_id'] )
			{
				$exist_item['bqty'] = apply_filters( 'wcwh_item_uom_conversion', $exist_item['product_id'], $exist_item['bqty'], $ref_row['product_id'] );
			}
			if ( isset( $exist_item['ref_item_id']) && $exist_item['ref_item_id'] != "0" )
				if(  ! $this->update_document_items_uqty( $exist_item['ref_item_id'] , $exist_item['bqty'], $exist_item['bunit'] , "-", $this->parent_status ) )
					return false;

			//Set new uqty for new Document. V1.0.2
			$ref_row = $this->get_detail( [ 'item_id'=>$update_item['ref_item_id'], 'doc_id'=>$update_item['ref_doc_id'] ], [], true );
			if( $ref_row && $ref_row['product_id'] != $update_item['product_id'] )
			{
				$update_item['bqty'] = apply_filters( 'wcwh_item_uom_conversion', $update_item['product_id'], $update_item['bqty'], $ref_row['product_id'] );
			}
			if ( $update_item['ref_item_id'] != "0" && ! $this->update_document_items_uqty( $update_item['ref_item_id'] , $update_item['bqty'], $update_item['bunit'] , "+", $this->parent_status ) )
				return false;
		}
		else if(  $update_item['ref_item_id'] != "0" && $exist_item['bqty'] != $update_item['bqty'] ) //User Change Qty  V1.0.2
		{
			//Set new uqty for change Qty.
			$amend_qty = $update_item['bqty'] - $exist_item['bqty'];
			$amend_unit = $update_item['bunit'] - $exist_item['bunit'];
			$plus_sign = "+"; //$update_item[bqty] > $exist_item[bqty] ? "+" : "-";

			$ref_row = $this->get_detail( [ 'item_id'=>$update_item['ref_item_id'], 'doc_id'=>$update_item['ref_doc_id'] ], [], true );
			if( $ref_row && $ref_row['product_id'] != $update_item['product_id'] )
			{
				$amend_qty = apply_filters( 'wcwh_item_uom_conversion', $update_item['product_id'], $amend_qty, $ref_row['product_id'] );
			}


			if ( ! $this->update_document_items_uqty( $update_item['ref_item_id'] , $amend_qty, $amend_unit , $plus_sign , $this->parent_status ) )
				return false;
		}
		//idw added - allow gr/misc hook for further process
		return apply_filters( 'after_update_items_uqty_handles', true, $exist_item, $update_item, $this->_doc_type, $this->_upd_uqty_flag );
	}
	/**
	 *	Action for Deleted Document Items : Un-set Uqty for linked document
	 */
	public function deleted_items_uqty_handles( $deleted_items ){

		if( ! $this->_upd_uqty_flag ) // V1.0.7 skip update uqty
			return true;
	
		if( ! $deleted_items || count($deleted_items) == 0 )
			return false;

		if ( count($this->parent_status) > 0 )
		{
			$arr_ref_doc = array();
			foreach( $deleted_items as $items )
			{
				if( $items['uqty'] > 0 ) // Qty in Used, Cannot do Deletion.
					return false; 

				$ref_row = $this->get_detail( [ 'item_id'=>$items['ref_item_id'], 'doc_id'=>$items['ref_doc_id'] ], [], true );
				if( $ref_row && $ref_row['product_id'] != $items['product_id'] )
				{
					$items['bqty'] = apply_filters( 'wcwh_item_uom_conversion', $items['product_id'], $items['bqty'], $ref_row['product_id'] );
				}

				if( isset( $items['ref_item_id'] ) && $items['ref_item_id'] > 0 )
				{
					if ( ! $this->update_document_items_uqty( $items['ref_item_id'] , $items['bqty'], $items['bunit'] , "-", $this->parent_status ) )
						return false;
					$arr_ref_doc[$items['ref_doc_id']] = $items['ref_doc_id'];
				}
			}

			//UPDATE Linked Status - V1.0.1
			if( count( $arr_ref_doc ) > 0 )
			{
				if ( ! $this->update_document_header_status_handles( $arr_ref_doc ) )
					return false;
			}
		}
		//idw added - allow gr/misc hook for further process
		return apply_filters( 'after_deleted_items_uqty_handles', true, $deleted_items, $this->_doc_type, $this->_upd_uqty_flag );
	}
	/**
	 *	Determine row item status
	 */
	public function determine_item_status( $bqty = 0, $uqty = 0, $status = array( 'full'=> '9', 'partial' => '4', 'empty' => '1') ){
		if( $uqty <= 0 ){
			return $status['empty'];
		}
		else if( $uqty < $bqty && $uqty > 0 ){
			return $status['partial'];
		}
		else if( $uqty >= $bqty ){
			return $status['full'];
		}
	}
	/**
	 *	Determine doc status
	 */
	public function determine_doc_status( $sta_array = array(), $status = array( 'full'=> '9', 'partial' => '4', 'empty' => '1') ){
		if( !$sta_array ) return 0;
		$sta_array = array_unique( $sta_array );
		
		return ( count( $sta_array ) == 1 )? $sta_array[0] : $status['partial'];
	}
}


?>