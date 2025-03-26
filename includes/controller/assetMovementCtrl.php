<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WC_AssetMovement" ) ) include_once( WCWH_DIR . "/includes/classes/asset-movement.php" ); 

if ( !class_exists( "WCWH_AssetMovement_Controller" ) ) 
{

class WCWH_AssetMovement_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_asset_movement";

	protected $primary_key = "id";

	public $Notices;
	public $className = "AssetMovement_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newAsMove',
	);

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WC_AssetMovement();
		//$this->Logic->set_section_id( $this->section_id );
	}

	public function get_section_id()
	{
		return $this->section_id;
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
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
				case 'complete':
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
				case "complete":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$movement = $this->Logic->get_asset_movement( [ 'id'=>$id ], [], true, [] );
							if( $movement['status'] < 6 )
							{
								$succ = false;
								$this->Notices->set_notice( 'bulk-validate', 'error' );
							}

							if( $succ )
							{
								$data = [ 'id'=>$id, 'status'=>9 ];
								$data['lupdate_by'] = $user_id;
								$data['lupdate_at'] = $now;
								$data['end_date'] = $now;
								
								$result = $this->Logic->action_handler( 'update', $data );
								if( ! $result['succ'] )
								{
									$succ = false;
									$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
									break;
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
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_form( $id = 0, $templating = true, $isView = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['new'],
		);

		if( $id )
		{
			$datas = $this->Logic->get_asset_movement( [ 'id'=>$id ], [], true, [] );
			
			if( $datas )
			{	
				$args['action'] = 'update';
				if( $isView ) $args['view'] = true;

				$args['data'] = $datas;
				
				$datas['details'] = $this->Logic->get_movement_linkage_by_movement( $datas['code'], -1, [ 'company'=>1, 'client'=>1 ] );
				if( $datas['details'] )
		        {
		        	foreach( $datas['details'] as $i => $row )
		        	{
		        		$datas['details'][$i]['status'] = $this->refs['status'][ $row['status'] ];
		        		$datas['details'][$i]['from'] = $row['comp_code'].' - '.$row['comp_name'];
		        		$datas['details'][$i]['to'] = $row['client_code'].' - '.$row['client_name'];
		        	}
		        }
				
				$Inst = new WCWH_Listing();
				$args['render'] = $Inst->get_listing( [
		        		'docno' => 'Document No.',
		        		//'from' 	=> 'From Company',
		        		'to'	=> 'Client',
		        		'doc_date' => 'Document Date',
		        		'status' => 'Status',
		        	], 
		        	$datas['details'], 
		        	[], 
		        	[], 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );
			}
		}

		do_action( 'wcwh_get_template', 'form/assetMovement-form.php', $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/assetMovementListing.php" ); 
			$Inst = new WCWH_AssetMovement_Listing();
			$Inst->set_section_id( $this->section_id );

			$temp = $filters; unset( $temp['status'] ); unset( $temp['qs'] ); $temp = array_filter( $temp );
			if( !empty( $temp ) ) $Inst->advSearch = array( 'isOn'=>1 );
			
			$Inst->filters = $filters;
			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$count = $this->Logic->count_statuses();
			if( $count ) $Inst->viewStats = $count;

			$datas = $this->Logic->get_asset_movement( $filters, $order, false, [ 'asset'=>1, 'company'=>1, 'warehouse'=>1 ] );
			$datas = ( $datas )? $datas : array();
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
} //class

}