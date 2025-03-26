<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Doc_Class" ) ) 
{

class WCWH_Doc_Class extends WC_DocumentTemplate 
{
	protected $section_id = "none";

	protected $tables = array();

	public $Notices;
	public $className = "Doc_Class";

	private $doc_type = 'none';

	public $useFlag = false;

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->setDocumentType( $this->doc_type );
	}
}

}
?>