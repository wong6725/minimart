<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_BankInService_Class" ) ) 
{

class WCWH_BankInService_Class extends WC_DocumentTemplate 
{
	protected $section_id = "wh_bankin_service";

	protected $tables = array();

	public $Notices;
	public $className = "BankInService_Class";

	private $doc_type = 'bank_in';

	public $useFlag = false;

	public $processing_stat = [];
	
	protected $warehouse = array();

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$this->setDocumentType( $this->doc_type );
		$this->setAccPeriodExclusive( [ $this->doc_type ] );

		$this->parent_status = array( 'full'=> '9', 'partial' => '6', 'empty' => '6' );

		$this->_no_details = true;
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}

	public function set_db_tables()
	{
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document" 			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",
			"warehouse"			=> $prefix."warehouse",
			"warehouse_tree"	=> $prefix."warehouse_tree",
		);
	}

	public function child_action_handle( $action , $header = array() , $details = array() )
	{	
		$succ = true;
		$outcome = array();

		wpdb_start_transaction();

		if( $header['doc_id'] )
		{
			$ref_stat = get_document_meta( $header['doc_id'], 'ref_status', 0, true );
			if( $ref_stat )
			{
				$this->parent_status['partial'] = $ref_stat;
				$this->parent_status['empty'] = $ref_stat;
			}
		}
		
		//UPDATE DOCUMENT
		$action = strtolower( $action );
		switch ( $action )
		{
			case "save":
			case "update":
				$succ = $this->document_action_handle( $action , $header , $details );
				if($action == 'update' && $succ)
				{
					if($header['bankAccID'] && $header['bankAccID'] != 'new')
					{
						$succ = delete_document_meta( $header['doc_id'], 'new_bankinfo_id' );
					}
				}
				if( ! $succ )
				{
					break; 
				}
				$doc_id = $this->header_item['doc_id'];
				$header_item = $this->header_item ;

				//Header Custom Field
				$succ = $this->header_meta_handle( $doc_id, $header_item );
				//$succ = $this->detail_meta_handle( $doc_id, $this->detail_item );
			break;
			case "delete":
				$doc_id = $header['doc_id'];
				$succ = $this->document_action_handle( $action , $header , $details );
				$header_item = $this->header_item;
				$metas = $this->get_document_meta( $doc_id);
				if($succ && $metas)
				{
					foreach($metas as $key => $value)
					{
						$metas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
					}
					if($metas['bankAccID'] == 'new' && $metas['new_bankinfo_id'] && $metas['customer_id'])
					{
						if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
						$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
						$data = [];
						$data['id'] = $metas['new_bankinfo_id'];
						$succ = $Inst->action_handler('delete',$data,'',false);
					}

				}
				//----
				//action handling for the bank_info when posted doc is delected?????
				//---
			break;
			case "approve":
			case "reject":
			case "complete":
			case "incomplete":
			case "close":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
			break;
			case "post":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
				$metas = $this->get_document_meta( $doc_id);
				if($succ && $metas)
				{
					foreach($metas as $key => $value)
					{
						$metas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
					}
					if($metas['bankAccID'] == 'new' && !$metas['new_bankinfo_id'] && $metas['customer_id'])
					{
						if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
						$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
						$datas = array_intersect_key( $metas, $Inst->get_defaultFields() );
						$succ = $Inst->action_handler('save',$datas,'',false);

						if($succ)
						{
							$succ = $this->add_document_meta_value('new_bankinfo_id',$succ['id'][0],$doc_id);
						}
					}
					elseif( $metas['bankAccID'] == 'new' && $metas['new_bankinfo_id'] && $metas['customer_id'])
					{
						if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
						$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
						$data = [];
						$data['id'] = $metas['new_bankinfo_id'];
						$succ = $Inst->action_handler('restore',$data,'',false);

						if( $succ )
						{	
							$datas = array_intersect_key( $metas, $Inst->get_defaultFields() );
							$datas['id'] = $metas['new_bankinfo_id'];
							$succ = $Inst->action_handler('update',$datas,'',false);
						}
					}

				}
			break;
			case "unpost":
				$succ = $this->document_action_handle( $action , $header , $details );
				$doc_id = $this->header_item['doc_id'];
				$metas = $this->get_document_meta( $doc_id);
				foreach($metas as $key => $value)
				{
					$metas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
				}
				if($succ && $metas['bankAccID'] == 'new' && $metas['new_bankinfo_id'] && $metas['customer_id'])
				{
					if ( !class_exists( "WCWH_BankInInfo_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/bankininfoCtrl.php" );
					$Inst = new WCWH_BankInInfo_Controller( $this->db_wpdb );
					$data = [];
					$data['id'] = $metas['new_bankinfo_id'];
					$succ = $Inst->action_handler('delete',$data,'',false);
				}
			break;
		}	
		
		$this->succ = apply_filters( "after_{$this->doc_type}_handler", $succ, $header, $details );
		
		wpdb_end_transaction( $succ );

		$outcome['succ'] = $succ; 
		$outcome['id'] = $doc_id;
		$outcome['data'] = $this->header_item;

		return $outcome;
	}

	public function count_statuses(  $wh = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = $this->dbName();

		$fld = "'all' AS status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "a.status, COUNT( a.status ) AS count ";
		$tbl = "{$dbname}{$this->tables['document']} a ";
		$cond = $wpdb->prepare( "AND a.status != %d ", -1 );
		$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
		if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
		$group = "GROUP BY a.status ";
		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

		if( $this->processing_stat )
		{
			$fld = "'process' AS status, COUNT( a.status ) AS count ";
			$tbl = "{$dbname}{$this->tables['document']} a ";
			$cond = "AND a.status IN( ".implode( ', ', $this->processing_stat )." ) ";
			$cond.= $wpdb->prepare( "AND a.doc_type = %s ", $this->doc_type );
			if( $wh ) $cond.= $wpdb->prepare( "AND warehouse_id = %s ", $wh );
			$sql.= " UNION ALL SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";
		}

		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$outcome = array();
		if( $results )
		{
			foreach( $results as $i => $row )
			{
				$outcome[ (string)$row['status'] ] = $row['count'];
			}
		}

		return $outcome;
	}
}

}
?>