<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Estate_Pricing_Rpt" ) ) 
{
	
class WCWH_Estate_Pricing_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "Estate_Pricing";

	public $tplName = array(
		'export' => 'exportEstate_Pricing',
		'print' => 'printEstate_Pricing',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();
	}
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"pricing"		=> $prefix."pricing",
			"pricingmeta"	=> $prefix."pricingmeta",
			"price"         => $prefix."price",
            "price_ref"     => $prefix."price_ref",
            "price_margin"  => $prefix."price_margin",

			"items"			=> $prefix."items",
			"items_tree"    => $prefix."items_tree",
            "itemsmeta"     => $prefix."itemsmeta",

			"category"		=> $wpdb->prefix."terms",
			"category_tree"	=> $prefix."item_category_tree",
			
			"status"		=> $prefix."status",
		);
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );
			$date_format = get_option( 'date_format' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					switch( $datas['export_type'] )
					{
						case 'foodboard':
							$datas['filename'] = 'Foodboard Pricing ';
						break;
						case 'estate':
						default:
							$datas['filename'] = 'Estate Pricing ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['on_date'] ) $datas['filename'].= " ".date( $date_format, strtotime( $datas['on_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['on_date'] ) ) $params['on_date'] = date( 'Y-m-d', strtotime( $datas['on_date'] ) );
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['on_date'] ) ) $params['on_date'] = date( 'Y-m-d', strtotime( $datas['on_date'] ) );
					if( !empty( $datas['client'] ) ) $params['client'] = $datas['client'];
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];

					$this->print_handler( $params, $datas );
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
	protected function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		//$default_column['title'] = [];

		//$default_column['default'] = [];

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		
		switch( $type )
		{
			case 'foodboard':
				return $this->get_foodboard_pricing( $params );
			break;
			case 'estate':
			default:
				return $this->get_estate_pricing( $params );
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$datas = $this->export_data_handler( $params );

		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		
		switch( $type )
		{
			case 'foodboard':
				$filename = "Foodboard-Pricing";
				if( $params['on_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['on_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Foodboard Pricing';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Foodboard Pricing';
				
				if( $params['on_date'] ) $document['heading']['title'].= " Date ".date_i18n( $date_format, strtotime( $params['on_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Item' => [ 'width'=>'35%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'35%', 'class'=>['leftered'] ],
					'UOM' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'DC Price' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Canteen Price' => [ 'width'=>'10%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$details = [];
					foreach( $datas as $i => $data )
					{
						$item = [];
						if( $data['item_code'] ) $item[] = $data['item_code'];
						if( $data['item_name'] ) $item[] = $data['item_name'];
						$data['item'] = implode( ' - ', $item );

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];
						$data['category'] = implode( ' - ', $category );

						$row = [

'item' => [ 'value'=>$data['item'], 'class'=>['leftered'] ],
'category' => [ 'value'=>$data['category'], 'class'=>['leftered'] ],
'uom' => [ 'value'=>$data['uom'], 'class'=>['leftered'] ],
'dc_price' => [ 'value'=>$data['dc_price'], 'class'=>['rightered'], 'num'=>1 ],
'canteen_price' => [ 'value'=>$data['canteen_price'], 'class'=>['rightered'], 'num'=>1 ],

						];

						$details[] = $row;
					}

					$document['detail'] = $details;
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
			break;
			case 'estate':
				$filename = "Estate-Pricing";
				if( $params['on_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['on_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Estate Pricing';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Estate Pricing';
				
				if( $params['on_date'] ) $document['heading']['title'].= " Date ".date_i18n( $date_format, strtotime( $params['on_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Item' => [ 'width'=>'35%', 'class'=>['leftered'] ],
					'Category' => [ 'width'=>'35%', 'class'=>['leftered'] ],
					'UOM' => [ 'width'=>'10%', 'class'=>['leftered'] ],
					'DC Price' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Canteen Price' => [ 'width'=>'10%', 'class'=>['rightered'] ],
				];
				if( $datas )
				{
					$details = [];
					foreach( $datas as $i => $data )
					{
						$item = [];
						if( $data['item_code'] ) $item[] = $data['item_code'];
						if( $data['item_name'] ) $item[] = $data['item_name'];
						$data['item'] = implode( ' - ', $item );

						$category = [];
						if( $data['category_code'] ) $category[] = $data['category_code'];
						if( $data['category_name'] ) $category[] = $data['category_name'];
						$data['category'] = implode( ' - ', $category );

						$row = [

'item' => [ 'value'=>$data['item'], 'class'=>['leftered'] ],
'category' => [ 'value'=>$data['category'], 'class'=>['leftered'] ],
'uom' => [ 'value'=>$data['uom'], 'class'=>['leftered'] ],
'dc_price' => [ 'value'=>$data['dc_price'], 'class'=>['rightered'], 'num'=>1 ],
'canteen_price' => [ 'value'=>$data['canteen_price'], 'class'=>['rightered'], 'num'=>1 ],

						];

						$details[] = $row;
					}

					$document['detail'] = $details;
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
			break;
		}

		exit;
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
			case 'export':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Report"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
			case 'print':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print'] ?>" 
					data-title="<?php echo $actions['print'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?> Report"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = '' )
	{
		$action_id = 'estate_pricing_export';
		$args = array(
			'setting'	=> $this->setting,
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'foodboard':
				do_action( 'wcwh_templating', 'report/export-fb_price-report.php', $this->tplName['export'], $args );
			break;
			case 'estate':
			default:
				do_action( 'wcwh_templating', 'report/export-et_price-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = '' )
	{
		$action_id = 'estate_pricing_export';
		$args = array(
			'setting'	=> $this->setting,
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'foodboard':
				do_action( 'wcwh_templating', 'report/export-fb_price-report.php', $this->tplName['print'], $args );
			break;
			case 'estate':
			default:
				do_action( 'wcwh_templating', 'report/export-et_price-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	Foodboard Pricing
	 */
	public function foodboard_pricing_report( $filters = array(), $order = array() )
	{
		$action_id = 'foodboard_pricing_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/fbPricingList.php" ); 
			$Inst = new WCWH_Foodboard_Pricing_Report();
			$Inst->seller = $this->seller;
			
			$on_date = current_time( 'Y-m-d' );
			
			$filters['on_date'] = empty( $filters['on_date'] )? $on_date : $filters['on_date'];

			if( $this->seller ) $filters['seller'] = $this->seller;

			$filter = [ 'status'=>1 ];
			if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'foodboard_client', 'foodboard_customer' ] ] );
			if( $wh )
			{
				if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
				{
					$client = is_json( $wh['foodboard_client'] )? json_decode( stripslashes( $wh['foodboard_client'] ), true ) : $wh['foodboard_client'];
					$filters['client'] = !empty( $filters['client'] )? $filters['client'] : $client[0];
					
					$customer = is_json( $wh['foodboard_customer'] )? json_decode( stripslashes( $wh['foodboard_customer'] ), true ) : $wh['foodboard_customer'];
					$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : $customer[0];
				}
			}
			//pd($filters);
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.dc_price, .canteen_price' => [ 'text-align'=>'right !important' ],
				'#dc_price a span, #canteen_price a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_foodboard_pricing( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	/**
	 *	Estate Pricing
	 */
	public function estate_pricing_report( $filters = array(), $order = array() )
	{
		$action_id = 'estate_pricing_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/etPricingList.php" ); 
			$Inst = new WCWH_Estate_Pricing_Report();
			$Inst->seller = $this->seller;
			
			$on_date = current_time( 'Y-m-d' );
			
			$filters['on_date'] = empty( $filters['on_date'] )? $on_date : $filters['on_date'];

			if( $this->seller ) $filters['seller'] = $this->seller;

			$filter = [ 'status'=>1 ];
			if( isset( $filters['seller'] ) ) $filter['seller'] = $filters['seller'];
			$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_client', 'estate_customer' ] ] );
			if( $wh )
			{
				if( empty( $filters['client'] ) && empty( $filters['customer'] ) )
				{
					$client = is_json( $wh['estate_client'] )? json_decode( stripslashes( $wh['estate_client'] ), true ) : $wh['estate_client'];
					$filters['client'] = !empty( $filters['client'] )? $filters['client'] : $client[0];
					
					$customer = is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'];
					$filters['customer'] = !empty( $filters['customer'] )? $filters['customer'] : $customer[0];
				}
			}
			//pd($filters);
			
			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );
			
			$Inst->styles = [
				'.dc_price, .canteen_price' => [ 'text-align'=>'right !important' ],
				'#dc_price a span, #canteen_price a span' => [ 'float'=>'right' ],
			];

			//$order = $Inst->get_data_ordering();

			$datas = [];
			if( ! $this->noList )
			{
				$datas = $this->get_estate_pricing( $filters, $order, [] );
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}
	
	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_foodboard_pricing( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
		
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

        $filter = [ 'status'=>1, 'indication'=>1 ];
        $wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [] );
        if( ! $wh ) return false;

        $filter = [ 'id'=>$filters['seller'] ];
        $pos = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [] );
        if( ! $pos ) return false;
        $filters['seller'] = $pos['code'];

        $filters['client_code'] = $filters['client'];
        unset( $filters['client'] ); unset( $filters['customer'] );
	
        $field = "a.code AS item_code, a.name AS item_name, a._uom_code AS uom ";
        $field.= ", cat.slug AS category_code, cat.name AS category_name ";
	
        $table = "{$this->tables['items']} a ";
        $table.= "LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";

        $subsql = "SELECT ancestor AS id FROM {$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

		$table.= "LEFT JOIN {$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

       	//------------------------------------------------------------DC
		$filter = $filters;
		$filter['seller'] = $wh['code'];
        $dcSql = $this->get_price_list( $filter );
        if( ! $dcSql ) return false;
    	
    	$field.= ", dcp.unit_price AS dc_price ";
        $table.= "LEFT JOIN ( {$dcSql} ) dcp ON dcp.item_id = a.id ";

        //------------------------------------------------------------Store
        $filter = $filters;
        unset( $filter['client_code'] );
        $posSql = $this->get_price_list( $filters );
        if( ! $posSql ) return false;
    	
    	$field.= ", posp.unit_price AS canteen_price ";
        $table.= "LEFT JOIN ( {$posSql} ) posp ON posp.item_id = a.id ";

        //------------------------------------------------------------
        if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND a.code IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.code = %d ", $filters['product'] );
		}

        if( ! isset( $filters['category'] ) ) $filters['category'] = $this->setting['foodboard_report']['categories'];
        if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( ! isset( $filters['store_type'] ) ) $filters['store_type'] = $this->setting['et_pricing_report']['store_type'];
		if( isset( $filters['store_type'] ) )
		{
			if( is_array( $filters['store_type'] ) )
			{
				$cond.= "AND a.store_type_id IN ('" .implode( "','", $filters['store_type'] ). "') ";
			}
			else
			{
				$cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type'] );
			}
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
                $cd[] = "a._sku LIKE '%".$kw."%' ";
                $cd[] = "a.name LIKE '%".$kw."%' ";
                $cd[] = "a.code LIKE '%".$kw."%' ";
                $cd[] = "a.serial LIKE '%".$kw."%' ";
                $cd[] = "a.serial2 LIKE '%".$kw."%' ";
	
                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );
	
            $cond.= ") ";
        }
	
        //order
		if( empty( $order ) )
		{
			$order = [ 'a.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_estate_pricing( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
        $wpdb = $this->db_wpdb;
        $prefix = $this->get_prefix();
	
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

        $filter = [ 'status'=>1, 'indication'=>1 ];
        $wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [] );
        if( ! $wh ) return false;

        $filter = [ 'id'=>$filters['seller'] ];
        $pos = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [] );
        if( ! $pos ) return false;
        $filters['seller'] = $pos['code'];

        $filters['client_code'] = $filters['client'];
        unset( $filters['client'] ); unset( $filters['customer'] );
	
        $field = "a.code AS item_code, a.name AS item_name, a._uom_code AS uom ";
        $field.= ", cat.slug AS category_code, cat.name AS category_name ";
	
        $table = "{$this->tables['items']} a ";
        $table.= "LEFT JOIN {$this->tables['category']} cat ON cat.term_id = a.category ";

        $subsql = "SELECT ancestor AS id FROM {$this->tables['category_tree']} ";
		$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

		$table.= "LEFT JOIN {$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

        $cond = "";
        $grp = "";
        $ord = "";
        $l = "";

       	//------------------------------------------------------------DC
		$filter = $filters;
		$filter['seller'] = $wh['code'];
        $dcSql = $this->get_price_list( $filter );
        if( ! $dcSql ) return false;
    	
    	$field.= ", dcp.unit_price AS dc_price ";
        $table.= "LEFT JOIN ( {$dcSql} ) dcp ON dcp.item_id = a.id ";

        //------------------------------------------------------------Store
        $filter = $filters;
        unset( $filter['client_code'] );
        $posSql = $this->get_price_list( $filters );
        if( ! $posSql ) return false;
    	
    	$field.= ", posp.unit_price AS canteen_price ";
        $table.= "LEFT JOIN ( {$posSql} ) posp ON posp.item_id = a.id ";

        //------------------------------------------------------------
        if( isset( $filters['product'] ) )
		{
			if( is_array( $filters['product'] ) )
				$cond.= "AND a.code IN ('" .implode( "','", $filters['product'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.code = %d ", $filters['product'] );
		}

        if( isset( $filters['category'] ) )
		{
			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
			else
			{
				$catcd = $wpdb->prepare( "ct.term_id = %d ", $filters['category'] );
				$catcd = $wpdb->prepare( "OR cat.term_id = %d ", $filters['category'] );
				$cond.= "AND ( {$catcd} ) ";
			}
		}
		if( ! isset( $filters['store_type'] ) ) $filters['store_type'] = $this->setting['et_pricing_report']['store_type'];
		if( isset( $filters['store_type'] ) )
		{
			if( is_array( $filters['store_type'] ) )
			{
				$cond.= "AND a.store_type_id IN ('" .implode( "','", $filters['store_type'] ). "') ";
			}
			else
			{
				$cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type'] );
			}
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
                $cd[] = "a._sku LIKE '%".$kw."%' ";
                $cd[] = "a.name LIKE '%".$kw."%' ";
                $cd[] = "a.code LIKE '%".$kw."%' ";
                $cd[] = "a.serial LIKE '%".$kw."%' ";
                $cd[] = "a.serial2 LIKE '%".$kw."%' ";
	
                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );
	
            $cond.= ") ";
        }
	
        //order
		if( empty( $order ) )
		{
			$order = [ 'a.code' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

		public function get_price_list( $filters = [], $run = false )
	    {
	        global $wcwh;
	        $wpdb = $this->db_wpdb;
	        $prefix = $this->get_prefix();

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

	        if( ! $filters['seller'] ) return false;
	        $seller = $filters['seller'];
	        $since = ( isset( $filters['on_date'] ) )? $filters['on_date']." 23:59:59" : current_time( 'Y-m-d 23:59:59' );

	        $scheme = $wpdb->prepare( "AND c.scheme = %s ", 'default' );
	        if( isset( $filters['client_code'] ) )
	        {
	            $scheme = $wpdb->prepare( "AND ( ( c.scheme = 'default' ) OR ( c.scheme = 'client_code' AND c.ref_id = %s ) ) ", $filters['client_code'] );
	        }

	    //---------------------------------------------------------------------------------------------------------
	        //Get Margined Price
	        $field = "i.id AS item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, mp.type AS price_type, p.since, p.created_by ";
	        $field.= ", p.created_at , mg.price_value AS margin, pr.unit_price AS uprice ";
	        $field.= ", @rn:= IF( mgc.meta_value IS NULL OR mgc.meta_value = 0, 0.01, mgc.meta_value ) AS rn 
            , ROUND( CASE 
            WHEN mgb.meta_value = 'ROUND' 
                THEN ROUND( ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mgb.meta_value = 'CEIL' 
                THEN CEIL( ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mgb.meta_value = 'FLOOR' 
                THEN FLOOR( ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) / @rn ) * @rn 
            WHEN mgb.meta_value IS NULL OR mgb.meta_value = 'DEFAULT' 
                THEN ROUND( pr.unit_price+( pr.unit_price*( mg.price_value/100 ) ), 2 ) 
            END, 2 ) AS unit_price ";

	        $table = "{$this->tables['items']} i ";

	        //----------------------------------------
	            $tbl = "{$this->tables['pricing']} a ";
	            $tbl.= "LEFT JOIN {$this->tables['price_margin']} b ON b.price_id = a.id AND b.status > 0 ";
	            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
	            $cd = $wpdb->prepare( "AND ( b.product_id = i.id OR b.product_id = 0 ) AND a.type = %s ", 'margin' );
	            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
	            $cd.= $wpdb->prepare( " AND c.seller = %s AND a.since <= %s ", $seller, $since );
	            $cd.= $scheme;
	            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
	            $l = "LIMIT 0,1 ";
	            $marginSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l} ";
	        //----------------------------------------

	        $table.= "LEFT JOIN {$this->tables['price_margin']} mg ON mg.id = ( {$marginSql} ) ";
	        $table.= "LEFT JOIN {$this->tables['pricing']} mp ON mp.id = mg.price_id ";
	        $table.= "LEFT JOIN {$this->tables['pricingmeta']} mga ON mga.pricing_id = mg.price_id AND mga.meta_key = 'margin_source' ";
	        $table.= "LEFT JOIN {$this->tables['pricingmeta']} mgb ON mgb.pricing_id = mg.price_id AND mgb.meta_key = 'round_type' ";
	        $table.= "LEFT JOIN {$this->tables['pricingmeta']} mgc ON mgc.pricing_id = mg.price_id AND mgc.meta_key = 'round_nearest' ";

	        //----------------------------------------
	            $tbl = "{$this->tables['pricing']} a ";
	            $tbl.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status > 0 ";
	            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
	            $cd = $wpdb->prepare( "AND b.product_id = i.id AND a.type = %s ", 'price' );
	            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
	            $cd.= $wpdb->prepare( " AND c.seller = mga.meta_value AND a.since <= %s ", $since );
	            $cd.= "AND ( c.scheme = 'default' ) ";
	            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
	            $l = "LIMIT 0,1 ";
	            $priceSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l} ";
	        //----------------------------------------

	        $table.= "LEFT JOIN {$this->tables['price']} pr ON pr.id = ( {$priceSql} ) ";
	        $table.= "LEFT JOIN {$this->tables['pricing']} p ON p.id = pr.price_id ";

	        $cond = "AND pr.price_id > 0 ";

	        if( $filters['product'] )
	        {
	            if( is_array( $filters['product'] ) )
	                $cond.= "AND i.code IN( '".implode( "', '", $filters['product'] )."' ) ";
	            else
	                $cond.= $wpdb->prepare( "AND i.code = %d ", $filters['product'] );
	        }

	        $margined = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

	    //---------------------------------------------------------------------------------------------------------
	        //Get Price  
	        $field = "i.id AS item_id, pr.price_id, p.docno, p.sdocno, p.code AS price_code, p.type AS price_type, p.since, p.created_by ";
	        $field.= ", p.created_at , 0 AS margin, pr.unit_price AS uprice, '0.01' AS rn, pr.unit_price ";

	        $table = "{$this->tables['items']} i ";

	        //----------------------------------------
	            $tbl = "{$this->tables['pricing']} a ";
	            $tbl.= "LEFT JOIN {$this->tables['price']} b ON b.price_id = a.id AND b.status > 0 ";
	            $tbl.= "LEFT JOIN {$this->tables['price_ref']} c ON c.price_id = a.id AND c.status > 0 ";
	            $cd = $wpdb->prepare( "AND b.product_id = i.id AND a.type = %s ", 'price' );
	            $cd.= $wpdb->prepare( "AND a.status > %d AND a.flag > %d ", 0, 0 );
	            $cd.= $wpdb->prepare( " AND c.seller = %s AND a.since <= %s ", $seller, $since );
	            $cd.= $scheme;
	            $o = "ORDER BY c.scheme_lvl DESC, a.created_at DESC, a.since DESC, a.id DESC ";
	            $l = "LIMIT 0,1 ";
	            $priceSql = "SELECT b.id FROM {$tbl} WHERE 1 {$cd} {$o} {$l} ";
	        //----------------------------------------

	        $table.= "LEFT JOIN {$this->tables['price']} pr ON pr.id = ( {$priceSql} ) ";
	        $table.= "LEFT JOIN {$this->tables['pricing']} p ON p.id = pr.price_id ";

	        $cond = "";

	        if( $filters['product'] )
	        {
	            if( is_array( $filters['product'] ) )
	                $cond.= "AND i.code IN( '".implode( "', '", $filters['product'] )."' ) ";
	            else
	                $cond.= $wpdb->prepare( "AND i.code = %d ", $filters['product'] );
	        }

	        $priced = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";

	    //---------------------------------------------------------------------------------------------------------
	        //Main
	        $field = "a.*";
	        $sub_ord = "ORDER BY created_at DESC, since DESC, price_id DESC ";
	        $table = "( ( {$margined} ) UNION ALL ( {$priced} ) {$sub_ord} ) a ";
	        $cond = "";
	        $grp = "GROUP BY a.item_id ";
	        $ord = "";
	        $lmt = "";

	        $query = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$lmt} ";
	        
	        if( $run ) $query = $wpdb->get_results( $query , ARRAY_A );

	        return $query;
	    }
	
} //class

}