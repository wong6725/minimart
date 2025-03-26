<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Setting_Controller" ) ) 
{

class WCWH_Setting_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_setting";

	public $Notices;
	public $className = "Setting_Controller";

	public $Logic;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();

		add_action( 'init', array( $this, 'setting_handler' ) );
	}

	public function get_section_id()
	{
		return $this->section_id;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_fragment( $type = 'save' )
	{
		
	}

	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$args = array(
			'hook'		=> $this->section_id.'_form',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'datas'		=> get_option( 'wcwh_option' ),
		);

		do_action( 'wcwh_get_template', 'setting.php', $args );
	}
	
} //class

}