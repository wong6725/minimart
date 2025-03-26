<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ItemRel_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item-rel.php" ); 

if ( !class_exists( "WCWH_ItemRel_Controller" ) ) 
{

class WCWH_ItemRel_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_items_relation";

	protected $primary_key = "id";

	public $Notices;
	public $className = "ItemRel_Controller";

	public $Logic;

	public $tplName = array(
		'new' => 'newItemRel',
		'import' => 'importItemRel',
		'export' => 'exportItemRel',
	);

	public $useFlag = false;
	
	private $unique_field = [];

	protected $warehouse = array();
	protected $view_outlet = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_logic();
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_ItemRel_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->useFlag = $this->useFlag;
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
	protected function get_defaultFields()
	{
		return array(
			'items_id' => 0,
			'wh_id' => '',
			'rel_type' => '',
			'sellable' => 0,
			'alert_level' => 0,
			'order_level' => 0,
			'expiration_days' => 0,
			'reorder_type' => 0,
			'status' => 1,
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
				case 'delete':
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
					$datas = $this->data_sanitizing( $datas );

					$extracted = $this->extract_data( $datas );
					$datas = $extracted['datas'];
					$metas = $extracted['metas'];

					$f = [ 
						'items_id' => $datas['items_id'], 
						'rel_type' => $datas['rel_type'] 
					];
					//$f[ $datas['rel_type'] ] = $datas[ $datas['rel_type'] ];
					$f['wh_id'] = !empty( $datas['wh_id'] )? $datas['wh_id'] : $this->warehouse['code'];
					$exists = $this->Logic->get_infos( $f, [], true );

					if( $exists )
					{
						$datas[ $this->get_primaryKey() ] = $exists['id'];
					}
					
					if( ! $datas[ $this->get_primaryKey() ] )
					{
						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
					}
					if( $datas[ $this->get_primaryKey() ] )
					{
						$datas = wp_parse_args( $datas, $this->get_defaultFields() );
					}
					if( $datas[ $this->get_primaryKey() ] > 0 && empty( $datas[ $datas['rel_type'] ] ) )
						$action = 'delete';
					if( ! $datas[ $this->get_primaryKey() ] && empty( $datas[ $datas['rel_type'] ] ) )
						$datas['status'] = 0;
					
					//$datas = $this->json_encoding( $datas );

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
							$outcome['id'][] = $result['id'];
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
								$outcome['id'][] = $result['id'];
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
					$datas['filename'] = 'item_relation';

					$params = [];
					$params['status'] = 1;
					$params['wh_id'] = $this->warehouse['code'];
					$params['rel_type'] = 'reorder_type';

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


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		$default_column['title'] = array( 'Name', 'Code', 'Serial', 'WH Code', 'Relation Type', 'Order Type', 'Status' );

		$default_column['default'] = array( 'name', 'code', 'serial', 'wh_id', 'rel_type', 'reorder_type', 'status' );

		$default_column['unique'] = array( 'serial' );

		$default_column['required'] = array( 'wh_id' );

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
		
		$lists = [];

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
			
			if( $data['reorder_type'] )
			{
				$dat = apply_filters( 'wcwh_get_order_type', [ 'code'=>$data['reorder_type'] ], [], true, [] );
				$data['reorder_type'] = ( $dat )? $dat['id'] : '';
			}

			//-----------------------------------------------------------------

			$items_id = 0; $curr = [];
			if( !empty( $unique ) )
			{
				foreach( $unique as $key )
				{
					if( ! empty( $data[ $key ] ) )
					{
						$found = apply_filters( 'wcwh_get_item', [ $key=>$data[ $key ] ], [], true, [] );
						if( $found )
						{
							$items_id = $found['id'];
							$curr = $found;
							break;
						}
					}
				}
			}
			
			if( $items_id )	//record found; update
			{
				$lists[$i] = [
					'items_id' => $items_id,
					'wh_id' => $data['wh_id'],
					'rel_type' => !empty( $data['rel_type'] )? $data['rel_type'] : 'reorder_type',
					'reorder_type' => $data['reorder_type'],
				];
			}
		}

		//pd($lists);exit;
		
		if( $succ && $lists )
		{
			wpdb_start_transaction( $this->db_wpdb );
			
			$this->unique_field = array();

			$action = "save";
			foreach( $lists as $i => $list )
			{	
				$outcome = $this->action_handler( $action, $list, [], false );
				if( ! $outcome['succ'] ) 
				{
					$succ = false;
					break;
				}
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
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="save" data-tpl="<?php echo $this->tplName['new'] ?>" 
					data-title="<?php echo $actions['save'] ?> Item Relation" data-modal="wcwhModalForm" 
					data-actions="close|submit" 
					title="Add <?php echo $actions['save'] ?> Item Relation"
				>
					<?php echo $actions['save'] ?> Item Relation
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
			case 'import':
				if( current_user_cans( [ 'import_'.$this->section_id ] ) ):
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="import" data-tpl="<?php echo $this->tplName['import'] ?>" 
					data-title="<?php echo $actions['import'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|import" 
					title="<?php echo $actions['import'] ?>"
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
					data-title="<?php echo $actions['export'] ?>" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?>"
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
			'wh_code'	=> $this->warehouse['code'],
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $id )
		{
			$filters = [ 'id' => $id ];
			//if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = $this->Logic->get_infos( $filters, [], true, [] );
			
			if( $datas )
			{	
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
			do_action( 'wcwh_templating', 'form/itemRel-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/itemRel-form.php', $args );
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
			'wh_code'	=> $this->warehouse['code'],
		);

		do_action( 'wcwh_templating', 'import/import-itemRel.php', $this->tplName['import'], $args );
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
			'wh_code'	=> $this->warehouse['code'],
		);

		do_action( 'wcwh_templating', 'export/export-itemRel.php', $this->tplName['export'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/itemRelListing.php" ); 
			$Inst = new WCWH_ItemRel_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;

			if( $this->warehouse['code'] ) $filters['wh_id'] = $this->warehouse['code'];
			$filters['rel_type'] = 'reorder_type';
			
			$Inst->filters = $filters;
			$Inst->advSearch_onoff( [ 'wh_id', 'rel_type' ] );

			$Inst->bulks = array( 
				'data-tpl' => 'remark', 
				'data-service' => $this->section_id.'_action', 
				'data-form' => 'edit-'.$this->section_id,
			);

			$wh = ( $this->warehouse['code'] )? $this->warehouse['code'] : '';
			$count = $this->Logic->count_statuses( $wh, $filters['rel_type'] );
			if( $count ) $Inst->viewStats = $count;

			$order = $Inst->get_data_ordering();
			$limit = $Inst->get_data_limit();

			$datas = $this->Logic->get_infos( $filters, $order, false, 
				[ 'item'=>1, 'category'=>1, 'reorder_type'=>1 ], [], $limit
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