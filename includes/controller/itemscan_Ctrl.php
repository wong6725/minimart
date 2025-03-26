<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Item_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item.php" ); 

if ( !class_exists( "WCWH_Item_Scan_Controller" ) ) 
{

class WCWH_Item_Scan_Controller extends WCWH_CRUD_Controller
{	
	protected $section_id = "wh_items_scan";

	protected $primary_key = "id";

	public $Notices;
	public $className = "Item_Scan_Controller";

	public $Logic;

	public $Rel;

	public $tplName = array(
		'new' => 'newItem',
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
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing"
		>
		<?php
			include_once( WCWH_DIR."/includes/listing/item_scanListing.php" ); 
			$Inst = new WCWH_Item_Scan_Listing();
			$Inst->set_section_id( $this->section_id );
			$Inst->useFlag = $this->useFlag;
			$Inst->warehouse = $this->warehouse;

			$Inst->render();
		?>
		</form>
		<?php
	}



	public function display_item( $filters=[], $templating = true, $isView = false )
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
		
		if( $filters )
		{
			if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			
			$datas = $this->Logic->get_infos( $filters, [], true, [ 'parent'=>true, 'uom'=>true, 'category'=>true, 'reorder_type'=>$this->warehouse['code'] ] );
			if( $datas )
			{	
				//metas
				$metas = get_items_meta( $datas['id'] );

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
			else
			{
				return;
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
	
} //class

}