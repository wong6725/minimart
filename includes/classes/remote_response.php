<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Remote_Response" ) ) 
{

class WCWH_Remote_Response extends WCWH_CRUD_Controller
{
	protected $section_id = "remote_response";

	protected $tables = array();

	public $Notices;
	public $className = "Remote_Response";
	
	public function __construct()
	{
		parent::__construct();

		$this->set_db_tables();
		
		$this->responding();
	}

	public function set_db_tables()
    {
        global $wpdb, $wcwh;
        $prefix = $wpdb->prefix;
		$pre = $wcwh->prefix;

        $this->tables = array(
        	"syncing" 	=> $pre.'syncing',
            "section" 	=> $pre.'section',
        );
    }

    public function responding()
    {
    	$datas = $_REQUEST;
    	$succ = true;
    	$response = [];

    	$key = [ 'wcwh_remote_api', 'wcwh_request_api', 'wcwh_check_api' ];
    	if( empty( $datas['handshake'] ) || ! in_array( $datas['handshake'], $key ) ) return;
		
    	if( ! $datas['secret'] || ! $datas['datas'] ) $succ = false;

    	$response = [ 
			'connection' => 1, 
			'arrived_at' => current_time( 'mysql' ),
		];

		switch( $datas['handshake'] )
		{
			case 'wcwh_remote_api':
				$response = $this->remote_api( $response, $datas );
			break;
			case 'wcwh_request_api':
				$response = $this->request_api( $response, $datas );
			break;
			case 'wcwh_check_api':
				$response = $this->test_respond( $response, $datas );
			break;
		}
		
		echo json_encode( $response );
		exit;
    }

    public function remote_api( $response = [], $datas = [] )
    {
    	$succ = true;

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		if( ! $wh ) $succ = false;
		
		if( $succ && ( 
			$datas['secret'] != md5( 'wcx1'.md5( $wh['code'].'wcwh_remote_api' ) ) && 
			$datas['secret'] != md5( 'wcx1'.md5( $this->refs['einv_id'].'wcwh_remote_api' ) )
		) ) $succ = false;

		if( $succ )
		{
			$response['authenticated'] = 1;

			update_option( 'wcwh_2ndlast_sync', get_option( 'wcwh_last_sync' ) );
			update_option( 'wcwh_last_sync', current_time( 'mysql' ) );

			$detail = $datas['datas'];
			unset( $datas['datas'] );
			$header = $datas;
			if( $detail )
			{
				if ( !class_exists( "WCWH_SYNC_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );
				$Inst = new WCWH_SYNC_Controller();

				$result = $Inst->sync_receiving( $header, $detail );
				if( $result )
				{
					$response['result'] = $result;
				}
			}
		}
		else
			$response['authenticated'] = 0;

		$response['succ'] = ( $succ )? 1 : 0;

		return $response;
    }

    public function request_api( $response = [], $datas = [] )
    {
    	$succ = true;

		$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		if( ! $wh ) $succ = false;

		if( $succ && ( 
			$datas['secret'] != md5( 'wcx1'.md5( $wh['code'].'wcwh_request_api' ) ) && 
			$datas['secret'] != md5( 'wcx1'.md5( $this->refs['einv_id'].'wcwh_request_api' ) )
		) ) $succ = false;

		if( $succ && empty( $datas['action'] ) ) $succ = false;
		
		if( $succ )
		{
			$response['authenticated'] = 1;

			$action = $datas['action'];
			$datas = $datas['datas'];

			if( $datas )
			{
				if ( !class_exists( "WCWH_SYNC_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );
				$Inst = new WCWH_SYNC_Controller();

				$result = $Inst->sync_responding( $action, $datas );
				if( $result )
				{
					$response['result'] = $result;
				}
				else
				{
					$succ = false;
				}
			}
		}
		else
			$response['authenticated'] = 0;

		$response['succ'] = ( $succ )? 1 : 0;
		
		return $response;
    }

    public function test_respond( $response = [], $datas = [] )
    {
    	$succ = false;
		if( $datas['secret'] === md5( 'wcx1'.md5( 'test_'.'wcwh_check_api' ) ) ) $succ = true;
		
		if( $succ )
		{
			$response['authenticated'] = 1;
			$response['mission'] = 'Test Remote Connection';
			$response['result'] = $datas['datas'];
		}
		
		return $response;
    }
	
} //class

new WCWH_Remote_Response();
}