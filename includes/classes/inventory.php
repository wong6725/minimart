<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Inventory_Class" ) ) 
{

class WCWH_Inventory_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_inventory";

	protected $tbl = "inventory";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "Inventory_Class";

	protected $warehouse = array();

	public function __construct( $db_wpdb = array() )
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}

	public function setWarehouse( $wh )
    {
    	$this->warehouse = $wh;
    }

	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"inventory"			=> $prefix.$this->tbl,
			"pos_arc"			=> $prefix."pos_arc",
			
			"main" 				=> $prefix.'items',
			"tree"				=> $prefix."items_tree",
			"meta"				=> $prefix."itemsmeta",
			
			"uom"				=> $prefix."uom",
			"item_group"		=> $prefix."item_group",
			"item_store_type" 	=> $prefix."item_store_type",
			"category"			=> $wpdb->prefix."terms",
			"category_tree"		=> $prefix."item_category_tree",
			"taxonomy"			=> $wpdb->prefix."term_taxonomy",
			"brand"				=> $prefix."brand",
			"reprocess_item"	=> $prefix."reprocess_item",
			"item_converse"		=> $prefix."item_converse",
			
			"product"			=> $wpdb->posts,
			"product_meta"		=> $wpdb->postmeta,
			
			"client"			=> $prefix."client",
			"supplier"			=> $prefix."supplier",
			"status"			=> $prefix."status",
			
			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",
			"transaction_weighted"	=> $prefix."transaction_weighted",
			
			"storage"				=> $prefix."storage",
			
			"document"				=> $prefix."document",
			"document_items"		=> $prefix."document_items",
			"document_meta"			=> $prefix."document_meta",

			"order"				=> $wpdb->posts,
			"order_meta"		=> $wpdb->postmeta,
			"order_items"		=> $wpdb->prefix."woocommerce_order_items",
			"order_itemmeta"	=> $wpdb->prefix."woocommerce_order_itemmeta",

			"selling_price"		=> $prefix."selling_price",

			"temp_inv"			=> "temp_inventory",
		);
	}

	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
            }
        }

        if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = ( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        if( isset( $args['db_suffix'] ) )
        {
        	$dbsuffix = $args['db_suffix'];
        }
        
        $field = "a.* ";
        $table = "{$dbname}{$this->tables['inventory']}{$dbsuffix} a ";
        $cond = "";
        $ord = "";

        $isItem = ( $args && $args['item'] )? true : false;
        $isUom = ( $args && $args['uom'] )? true : false;
        if( $isItem || $isUom )
        {
            $field.= ", prdt.name AS prdt_name, prdt._sku AS sku, prdt.code AS prdt_code, prdt.serial AS prdt_serial, prdt._uom_code AS uom_id, prdt._self_unit AS self_unit, prdt._parent_unit AS parent_unit ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['main']} prdt ON prdt.id = a.prdt_id ";

            $field.= ", meta_a.meta_value AS inconsistent_unit ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['meta']} meta_a ON meta_a.items_id = prdt.id AND meta_a.meta_key = 'inconsistent_unit' ";
        }
        if( $isUom )
        {
            $field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['uom']} uom ON uom.code = prdt._uom_code ";
        }

        if( isset( $filters['id'] ) )
        {
            if( is_array( $filters['id'] ) )
                $cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id = %d ", $filters['id'] );
        }
        if( isset( $filters['not_id'] ) )
        {
            if( is_array( $filters['not_id'] ) )
                $cond.= "AND a.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id != %d ", $filters['not_id'] );
        }
        if( isset( $filters['warehouse_id'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
        }
        if( isset( $filters['strg_id'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
        }
        if( isset( $filters['prdt_id'] ) )
        {
            $cond.= $wpdb->prepare( "AND a.prdt_id = %d ", $filters['prdt_id'] );
        }

        $corder = array();
        //group
        if( !empty( $group ) )
        {
            $grp.= "GROUP BY ".implode( ", ", $group )." ";
        }

        //order
        if( empty( $order ) )
        {
        	$order = [ 'a.id' => 'ASC' ];
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

	/*
SELECT a.id, a.name, a._sku, a.code, a.serial, a._uom_code, a.parent, a.grp_id, a.store_type_id, a.category, a.status, a.flag 
, group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code 
, group_concat( distinct ta.id order by t.level desc separator ',' ) as breadcrumb_id 
, group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status 
, grp.name AS grp_name, grp.code AS grp_code 
, store.name AS store_name, store.code AS store_code 
, cat.name AS cat_name, cat.slug AS cat_slug 
, brand.id AS brand_id, brand.name AS brand_name, brand.code AS brand_code, brand.brand_no 
, iin.meta_value AS inconsistent_unit
, c.base_id, c.base_unit AS conversion  
, i.warehouse_id AS wh_code, i.strg_id
, @qty:= i.qty / IFNULL( c.base_unit, 1 ) AS qty, ( @qty - FLOOR( @qty ) ) * IFNULL( c.base_unit, 1 ) AS qty_
, @aqty:= i.allocated_qty / IFNULL( c.base_unit, 1 ) AS allocated_qty, ( @aqty - FLOOR( @aqty ) ) * IFNULL( c.base_unit, 1 ) AS allocated_qty_
, i.unit, i.allocated_unit
, @sqty:= i.total_sales_qty / IFNULL( c.base_unit, 1 ) AS total_sales_qty, ( @sqty - FLOOR( @sqty ) ) * IFNULL( c.base_unit, 1 ) AS total_sales_qty_
, i.total_sales_unit
, @tin:= i.total_in / IFNULL( c.base_unit, 1 ) AS total_in, ( @tin - FLOOR( @tin ) ) * IFNULL( c.base_unit, 1 ) AS total_in_
, @tout:= i.total_out / IFNULL( c.base_unit, 1 ) AS total_out, ( @tout - FLOOR( @tout ) ) * IFNULL( c.base_unit, 1 ) AS total_out_ 
FROM wp_stmm_wcwh_items a 
INNER JOIN wp_stmm_wcwh_items_tree t ON t.descendant = a.id 
INNER JOIN wp_stmm_wcwh_items ta force index(primary) ON ta.id = t.ancestor 
INNER JOIN wp_stmm_wcwh_items_tree tt ON tt.ancestor = a.id 
LEFT JOIN wp_stmm_wcwh_item_group grp ON grp.id = a.grp_id 
LEFT JOIN wp_stmm_wcwh_item_store_type store ON store.id = a.store_type_id 
LEFT JOIN wp_stmm_terms cat ON cat.term_id = a.category 
LEFT JOIN wp_stmm_terms ct ON ct.term_id = ( 
	SELECT ancestor AS id 
	FROM wp_stmm_wcwh_item_category_tree 
	WHERE 1 AND descendant = cat.term_id 
	ORDER BY level DESC LIMIT 0,1 
) 
LEFT JOIN wp_stmm_wcwh_itemsmeta ibr ON ibr.items_id = a.id AND ibr.meta_key = '_brand' 
LEFT JOIN wp_stmm_wcwh_brand brand ON brand.code = ibr.meta_value 
LEFT JOIN wp_stmm_wcwh_itemsmeta iin ON iin.items_id = a.id AND iin.meta_key = 'inconsistent_unit' 
LEFT JOIN wp_stmm_wcwh_item_converse c ON c.item_id = a.id
LEFT JOIN (
	SELECT c.base_id AS prdt_id, i.warehouse_id, i.strg_id
	, SUM( IFNULL( i.qty, 0 ) * c.base_unit ) AS qty, SUM( IFNULL( i.allocated_qty, 0 ) * c.base_unit ) AS allocated_qty
	, SUM( i.unit ) AS unit, SUM( i.allocated_unit ) AS allocated_unit
	, SUM( IFNULL( i.total_sales_qty, 0 ) * c.base_unit ) AS total_sales_qty, SUM( i.total_sales_unit ) AS total_sales_unit
	, SUM( IFNULL( i.total_in, 0 ) * c.base_unit ) AS total_in, SUM( IFNULL( i.total_out, 0 ) * c.base_unit ) AS total_out 
	FROM wp_stmm_wcwh_inventory i 
	LEFT JOIN wp_stmm_wcwh_item_converse c ON c.item_id = i.prdt_id
	WHERE 1 AND i.warehouse_id = '1025-MWT3' AND i.strg_id = '1'
	GROUP BY c.base_id
) i ON i.prdt_id = c.base_id
WHERE 1 AND a.status = 1 
GROUP BY a.code, a.serial, a.id 
ORDER BY breadcrumb_code asc , a.code ASC ;
	*/
	public function get_inventory( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$opts = $this->setting[ $this->section_id ];

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
			}
		}

		if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }
		
		$field = "a.id, a.name, a._sku, a.code, a.serial, a._uom_code, a.parent 
			, a.grp_id, a.store_type_id, a.category, a.status, a.flag ";

		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		//tree concat
		$cgroup = array();
		$isTree = ( $args && $args['tree'] )? true : false;
		if( $isTree )
		{
			$field.= ", group_concat( distinct ta.code order by t.level desc separator ',' ) as breadcrumb_code ";
			//$field.= ", group_concat( distinct ta.serial order by t.level desc separator ',' ) as breadcrumb_serial ";
			$field.= ", group_concat( distinct ta.id order by t.level desc separator ',' ) as breadcrumb_id ";
			$field.= ", group_concat( ta.status order by t.level desc separator ',' ) as breadcrumb_status ";
			$table.= "INNER JOIN {$dbname}{$this->tables['tree']} t ON t.descendant = a.id ";
			$table.= "INNER JOIN {$dbname}{$this->tables['main']} ta force index(primary) ON ta.id = t.ancestor ";
			$table.= "INNER JOIN {$dbname}{$this->tables['tree']} tt ON tt.ancestor = a.id ";

			$cgroup = [ "a.code", "a.serial", "a.id " ];
		}

		//join parent
		$isParent = ( $args && $args['parent'] )? true : false;
		if( $isParent )
		{
			$field.= ", prt.name AS prt_name, prt._sku AS prt_sku, prt._uom_code AS prt_uom_code ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['main']} prt ON prt.id = a.parent ";
		}

		$isUom = ( $args && $args['uom'] )? true : false;
		if( $isUom )
		{
			$field.= ", uom.name AS uom_name, uom.code AS uom_code, uom.fraction AS uom_fraction ";
			$table.= "LEFT JOIN {$dbname}{$this->tables['uom']} uom ON uom.code = a._uom_code ";
		}

		$isGrp = ( $args && $args['group'] )? true : false;
		if( $isGrp )
		{
			$field.= ", grp.name AS grp_name, grp.code AS grp_code ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		}

		$isStore = ( $args && $args['store'] )? true : false;
		if( $isStore )
		{
			$field.= ", store.name AS store_name, store.code AS store_code ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['item_store_type']} store ON store.id = a.store_type_id  ";
		}

		$isCat = ( $args && $args['category'] )? true : false;
		if( $isCat )
		{	
			$field.= ", cat.name AS cat_name, cat.slug AS cat_slug ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = a.category ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";

			$table.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

			if( is_array( $filters['category'] ) )
			{
				$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
				$cond.= "AND ( {$catcd} ) ";
			}
		}

		$isBrand = ( $args && $args['brand'] )? true : false;
		if( $isBrand )
		{
			$field.= ", brand.id AS brand_id, brand.name AS brand_name, brand.code AS brand_code, brand.brand_no ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['meta']} ibr ON ibr.items_id = a.id AND ibr.meta_key = '_brand' ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['brand']} brand ON brand.code = ibr.meta_value ";

			if( isset( $filters['_brand'] ) )
			{
				if( is_array( $filters['_brand'] ) )
					$cond.= "AND brand.code IN ('" .implode( "','", $filters['_brand'] ). "') ";
				else
					$cond.= $wpdb->prepare( "AND brand.code = %d ", $filters['_brand'] );
			}
		}

		$isInconsistent = ( $args && $args['inconsistent'] )? true : false;
		if( $isInconsistent )
		{
			$field.= ", iin.meta_value AS inconsistent_unit ";
			$table.= " LEFT JOIN {$dbname}{$this->tables['meta']} iin ON iin.items_id = a.id AND iin.meta_key = 'inconsistent_unit' ";

			if( isset( $filters['inconsistent_unit'] ) )
			{
				$cond.= $wpdb->prepare( "AND iin.meta_value = %s ", $filters['inconsistent_unit'] );
			}
		}

		$field.= ", c.base_id, c.base_unit AS conversion ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} c ON c.item_id = a.id ";

		//Inventory subquery-------------------------------------------------------------------------

		$cd = "";
		if( isset( $filters['wh_code'] ) )
        {
            $cd.= $wpdb->prepare( " AND i.warehouse_id = %s ", $filters['wh_code'] );
        }
        if( isset( $filters['strg_id'] ) )
        {
            $cd.= $wpdb->prepare( " AND i.strg_id = %s ", $filters['strg_id'] );
        }

		$subsql = "SELECT c.base_id AS prdt_id, i.warehouse_id, i.strg_id
			, SUM( IFNULL( i.qty, 0 ) * IFNULL(c.base_unit,1) ) AS qty
			, SUM( IFNULL( i.reserved_qty, 0 ) * IFNULL(c.base_unit,1) ) AS reserved_qty
			, SUM( IFNULL( i.allocated_qty, 0 ) * IFNULL(c.base_unit,1) ) AS allocated_qty
			, SUM( i.unit ) AS unit, SUM( i.reserved_unit ) AS reserved_unit, SUM( i.allocated_unit ) AS allocated_unit
			, SUM( IFNULL( i.total_sales_qty, 0 ) * IFNULL(c.base_unit,1) ) AS total_sales_qty
			, SUM( i.total_sales_unit ) AS total_sales_unit
			, SUM( IFNULL( i.total_in, 0 ) * IFNULL(c.base_unit,1) ) AS total_in
			, SUM( IFNULL( i.total_out, 0 ) * IFNULL(c.base_unit,1) ) AS total_out 
			FROM {$dbname}{$this->tables['inventory']} i 
			LEFT JOIN {$dbname}{$this->tables['item_converse']} c ON c.item_id = i.prdt_id 
			WHERE 1 {$cd} GROUP BY c.base_id ";

		$field.= ", i.warehouse_id AS wh_code, i.strg_id
			, @qty:= i.qty / IFNULL( c.base_unit, 1 ) AS qty, ( @qty - FLOOR( @qty ) ) * IFNULL( c.base_unit, 1 ) AS qty_
			, @rqty:= i.reserved_qty / IFNULL( c.base_unit, 1 ) AS reserved_qty
			, ( @rqty - FLOOR( @rqty ) ) * IFNULL( c.base_unit, 1 ) AS reserved_qty_
			, @aqty:= i.allocated_qty / IFNULL( c.base_unit, 1 ) AS allocated_qty
			, ( @aqty - FLOOR( @aqty ) ) * IFNULL( c.base_unit, 1 ) AS allocated_qty_
			, i.unit, i.reserved_unit, i.allocated_unit
			, @sqty:= i.total_sales_qty / IFNULL( c.base_unit, 1 ) AS total_sales_qty
			, ( @sqty - FLOOR( @sqty ) ) * IFNULL( c.base_unit, 1 ) AS total_sales_qty_
			, i.total_sales_unit
			, @tin:= i.total_in / IFNULL( c.base_unit, 1 ) AS total_in, ( @tin - FLOOR( @tin ) ) * IFNULL( c.base_unit, 1 ) AS total_in_
			, @tout:= i.total_out / IFNULL( c.base_unit, 1 ) AS total_out, ( @tout - FLOOR( @tout ) ) * IFNULL( c.base_unit, 1 ) AS total_out_
			, @bal:= @tin - @tout AS balance ";

		$table.= "LEFT JOIN ( {$subsql} ) i ON i.prdt_id = c.base_id ";

		//-----------------------------------------------------------------------------------------------

		if( isset( $filters['stock_condition'] ) )
		{
			if( $filters['stock_condition'] == 'yes' )
			{	
				if( $opts['use_allocate'] )
					$cond.= "AND i.total_in - i.total_out - i.allocated_qty > 0 ";
				else
					$cond.= "AND i.total_in - i.total_out > 0 ";
			}
			else if( $filters['stock_condition'] == 'no' )
			{	
				if( $opts['use_allocate'] )
					$cond.= "AND i.total_in - i.total_out - i.allocated_qty <= 0 ";
				else
					$cond.= "AND i.total_in - i.total_out <= 0 ";
			}
			else if( $filters['stock_condition'] == 'zero' )
			{	
				if( $opts['use_allocate'] )
					$cond.= "AND i.total_in - i.total_out - i.allocated_qty = 0 ";
				else
					$cond.= "AND i.total_in - i.total_out = 0 ";
			}
			else if( $filters['stock_condition'] == 'below' )
			{	
				if( $opts['use_allocate'] )
					$cond.= "AND i.total_in - i.total_out - i.allocated_qty < 0 ";
				else
					$cond.= "AND i.total_in - i.total_out < 0 ";
			}
		}

		if( isset( $filters['reserve_condition'] ) )
		{
			if( $filters['reserve_condition'] == 'yes' )
			{	
				$cond.= "AND i.reserved_qty > 0 ";
			}
			else if( $filters['reserve_condition'] == 'no' )
			{	
				$cond.= "AND i.reserved_qty <= 0 ";
			}
		}
	
		if( empty( $args['no_returnable'] ) )
		{
			$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} rti ON rti.items_id = a.id AND rti.meta_key = 'returnable_item' ";
			$cond.= "AND ( rti.meta_value IS NULL OR rti.meta_value = '' OR rti.meta_value <= 0 ) ";
		}

		$mspo_items = $this->setting['mspo_hide']['items'];
		if( !empty( $mspo_items ) && !isset( $args['mspo'] ) )
		{
			$cond.= "AND a.id NOT IN ('" .implode( "','", $mspo_items ). "') ";
		}

		if( isset( $filters['item_id'] ) )
		{
			if( $isTree )
            {
                if( is_array( $filters['item_id'] ) )
                    $cond.= "AND ( tt.descendant IN ('" .implode( "','", $filters['item_id'] ). "') OR t.ancestor IN ('" .implode( "','", $filters['item_id'] ). "') ) ";
                else
                    $cond.= $wpdb->prepare( "AND ( tt.descendant = %d OR t.ancestor = %d ) ", $filters['item_id'], $filters['item_id'] );
            }
            else
            {
                if( is_array( $filters['item_id'] ) )
                    $cond.= "AND a.id IN ('" .implode( "','", $filters['item_id'] ). "') ";
                else
                    $cond.= $wpdb->prepare( "AND a.id = %d ", $filters['item_id'] );
            }
		}
		if( isset( $filters['name'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.name = %s ", $filters['name'] );
		}
		if( isset( $filters['_sku'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._sku = %s ", $filters['_sku'] );
		}
		if( isset( $filters['code'] ) )
		{
			if( is_array( $filters['code'] ) )
                $cond.= "AND a.code IN ('" .implode( "','", $filters['code'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.code = %s ", $filters['code'] );
		}
		if( isset( $filters['serial'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.serial = %s ", $filters['serial'] );
		}
		if( isset( $filters['product_type'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.product_type = %s ", $filters['product_type'] );
		}
		if( isset( $filters['_material'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._material = %s ", $filters['_material'] );
		}
		if( isset( $filters['_uom_code'] ) )
		{
			if( is_array( $filters['_uom_code'] ) )
				$cond.= "AND a._uom_code IN ('" .implode( "','", $filters['_uom_code'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a._uom_code = %s ", $filters['_uom_code'] );
		}
		if( isset( $filters['_tax_status'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._tax_status = %s ", $filters['_tax_status'] );
		}
		if( isset( $filters['_tax_class'] ) )
		{
			$cond.= $wpdb->prepare( "AND a._tax_class = %s ", $filters['_tax_class'] );
		}
		if( isset( $filters['grp_id'] ) )
		{
			if( is_array( $filters['grp_id'] ) )
				$cond.= "AND a.grp_id IN ('" .implode( "','", $filters['grp_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.grp_id = %s ", $filters['grp_id'] );
		}
		if( isset( $filters['store_type_id'] ) )
		{
			if( is_array( $filters['store_type_id'] ) )
				$cond.= "AND a.store_type_id IN ('" .implode( "','", $filters['store_type_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type_id'] );
		}
		if( ! $isCat && isset( $filters['category'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.category = %s ", $filters['category'] );
		}
		if( isset( $filters['parent'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.parent = %s ", $filters['parent'] );
		}
		if( isset( $filters['ref_prdt '] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_prdt  = %s ", $filters['ref_prdt '] );
		}
		if( isset( $filters['action_by'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( a.created_by = %d OR a.lupdate_by = %d ) ", $filters['action_by'], $filters['action_by'] );
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

				if( $isParent )
				{
					$cd[] = "prt._sku LIKE '%".$kw."%' ";
					$cd[] = "prt.name LIKE '%".$kw."%' ";
				}

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

			unset( $filters['status'] );
		}

		$corder = array();
        //status
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
            $cond.= $wpdb->prepare( "AND a.status != %d ", -1 );

            $field.= ", IF( stat.status <= 0, stat.title, '' ) AS status_name ";
            $table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
        }
        //flag
        if( isset( $filters['flag'] ) && $filters['flag'] != "" )
        {   
            $cond.= $wpdb->prepare( "AND a.flag = %s ", $filters['flag'] );
        }
        if( $this->useFlag )
        {
             $table.= "LEFT JOIN {$dbname}{$this->tables['status']} flag ON flag.status = a.flag AND flag.type = 'flag' ";
             $corder["flag.order"] = "DESC";
        }

        $isTreeOrder = ( $args && $args['treeOrder'] )? true : false;
        if( $isTree && $isTreeOrder )
        {
        	$corder[ $args['treeOrder'][0] ] = $args['treeOrder'][1];
        }

        $isUse = ( $args && isset( $args['usage'] ) )? true : false;
		if( $isUse )
		{
			$cond.= $wpdb->prepare( "AND ( ( a.status >= %d AND a.flag = %d ) OR ( i.id != %d ) ) ", $args['usage'], 1, 0 );
		}

		//group
		$group = array_merge( $cgroup, $group );
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'a.code' => 'ASC' ];
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

		/*
		 * Deprecated as sql upgraded
		public function get_inventory_alters( $datas = array(), $filters = array() )
		{
			if( $datas )
			{
				if ( !class_exists( "WCWH_Item_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item.php" ); 
				$ITEM = new WCWH_Item_Class();

				$prdts = []; $idx = []; $childs = [];
				foreach( $datas as $i => $item )
				{
					$item['idx'] = $i;
					$prdts[ $item['id'] ] = $item;

					$datas[$i]['_qty'] = $item['qty'];
					$datas[$i]['_allocated_qty'] = $item['allocated_qty'];
					$datas[$i]['_total_sales_qty'] = $item['total_sales_qty'];

					if( $item['parent'] > 0 )
					{
						$base_id = 0;
						$datas[$i]['converse_rule'] = $conversion = $ITEM->uom_conversion( $item['id'], 0, $base_id, 'qty', [ 'seller'=>$filters['seller'] ] );

						$datas[$i]['base_id'] = $base_id;
						$datas[$i]['conversion'] = $conversion[ $item['id'] ]['base_unit'];

						//child
						$childs[ $item['id'] ] = [
							'idx' => $i,
							'base_id' => $base_id,
							'qty' => $item['qty'] * $conversion[ $item['id'] ]['base_unit'],
							'unit' => $item['unit'],
							'allocated_qty' => $item['allocated_qty'] * $conversion[ $item['id'] ]['base_unit'],
							'allocated_unit' => $item['allocated_unit'],
							'total_sales_qty' => $item['total_sales_qty'] * $conversion[ $item['id'] ]['base_unit'],
							'total_sales_unit' => $item['total_sales_unit'],
							'total_in' => $item['total_in'] * $conversion[ $item['id'] ]['base_unit'],
							'total_out' => $item['total_out'] * $conversion[ $item['id'] ]['base_unit'],
						];
					}
				}
				
				if( $childs )
				{
					foreach( $childs as $id => $child )
					{
						if( !empty( $prdts[ $child['base_id'] ] ) )
						{
							$prdt = $prdts[ $child['base_id'] ];
							$idx = $prdt['idx'];

							//update base to self
							$jdx = $child['idx'];
							$conversion = $datas[$jdx]['converse_rule'];
							$base_unit = ( $conversion[ $id ]['base_unit'] )? $conversion[ $id ]['base_unit'] : 1;

							$child_qty = $datas[$idx]['qty'] / $base_unit;
							$datas[$jdx]['qty']+= floor( $child_qty );
							$datas[$jdx]['qty_']+= round( ( $child_qty - floor( $child_qty ) ) * $base_unit );

							$child_qty = $datas[$idx]['allocated_qty'] / $base_unit;
							$datas[$jdx]['allocated_qty']+= floor( $child_qty );
							$datas[$jdx]['allocated_qty_']+= round( ( $child_qty - floor( $child_qty ) ) * $base_unit );

							$child_qty = $datas[$idx]['total_sales_qty'] / $base_unit;
							$datas[$jdx]['total_sales_qty']+= floor( $child_qty );
							$datas[$jdx]['total_sales_qty_']+= round( ( $child_qty - floor( $child_qty ) ) * $base_unit );

							$child_qty = $datas[$idx]['total_in'] / $base_unit;
							$datas[$jdx]['total_in']+= floor( $child_qty );
							$datas[$jdx]['total_in_']+= round( ( $child_qty - floor( $child_qty ) ) * $base_unit );

							$child_qty = $datas[$idx]['total_out'] / $base_unit;
							$datas[$jdx]['total_out']+= floor( $child_qty );
							$datas[$jdx]['total_out_']+= round( ( $child_qty - floor( $child_qty ) ) * $base_unit );

							//update to base item
							$datas[$idx]['qty']+= $child['qty'];
							$datas[$idx]['unit']+= $child['unit'];
							$datas[$idx]['allocated_qty']+= $child['allocated_qty'];
							$datas[$idx]['allocated_unit']+= $child['allocated_unit'];
							$datas[$idx]['total_sales_qty']+= $child['total_sales_qty'];
							$datas[$idx]['total_sales_unit']+= $child['total_sales_unit'];
							$datas[$idx]['total_in']+= $child['total_in'];
							$datas[$idx]['total_out']+= $child['total_out'];
						}
					}
				}
			}

			return $datas;
		}*/

	public function count_statuses()
	{
		$wpdb = $this->db_wpdb;

		if( $this->warehouse['view_outlet'] && $this->warehouse['id'] )
        {
        	$dbname = $this->warehouse['dbname'];

            $dbname = ( $dbname )? $dbname : get_warehouse_meta( $this->warehouse['id'], 'dbname', true );
            $dbname = $dbname.".";
        }

		$fld = "'all' AS status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );

		$sql1 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		$fld = "status, COUNT( status ) AS count ";
		$tbl = "{$dbname}{$this->tables['main']} ";
		$cond = $wpdb->prepare( "AND status != %d ", -1 );
		$group = "GROUP BY status ";
		$sql2 = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$group} ";

		$sql = $sql1." UNION ALL ".$sql2;

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

	public function get_transaction( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
			}
		}

		if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $field = "b.docno, b.doc_id, b.doc_type, hs.name AS supplier, hc.name AS client, b.doc_post_date ";
		$field.= ", a.product_id, a.bqty, a.bunit
			, IF( a.plus_sign = '+', a.weighted_total, 0 ) AS total_price, IF( a.plus_sign = '+', a.weighted_price, 0 ) AS unit_price 
			, IF( a.plus_sign = '-', a.weighted_total, 0 ) AS total_cost, IF( a.plus_sign = '-', a.weighted_price, 0 ) AS unit_cost
			, a.plus_sign ";
		$field.= ", c.from_prdt_id, c.from_qty, ROUND( a.bqty / c.from_qty, 2 ) AS conversion_rate
			, i.code AS ccode, i.name AS cname, i._uom_code AS cuom ";
		
		$table = "{$dbname}{$this->tables['transaction_items']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_conversion']} c ON c.hid = a.hid AND c.item_id = a.item_id AND c.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['main']} i ON i.id = c.from_prdt_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document']} h ON h.doc_id = b.doc_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'supplier_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['supplier']} hs ON hs.code = h1.meta_value ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} hc ON hc.code = h2.meta_value ";

		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$cond.= $wpdb->prepare( "AND a.status != %d AND b.status != %d ", 0, 0 );

		if( isset( $filters['product_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.product_id = %s ", $filters['product_id'] );
		}
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['strg_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
		}
		if( isset( $filters['transact'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.plus_sign = %s ", $filters['transact'] );
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'b.doc_post_date' => 'ASC', 'a.did' => 'ASC' ];
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

	public function get_reserved( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
			}
		}

		if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $field = "h.doc_id, h.docno, h.doc_date, h.doc_type, hc.name AS client, h1.meta_value AS ref_doc ";
		$field.= ", d.bqty, d.bunit
			, i.code AS ccode, i.name AS cname, i._uom_code AS cuom ";

		$table = "{$dbname}{$this->tables['document']} h ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} d ON d.doc_id = h.doc_id AND d.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['main']} i ON i.id = d.product_id ";

		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} h2 ON h2.doc_id = h.doc_id AND h2.item_id = 0 AND h2.meta_key = 'client_company_code' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['client']} hc ON hc.code = h2.meta_value ";

		$cond = "AND h.doc_type IN ( 'delivery_order' ) ";
		$grp = "";
		$ord = "";
		$l = "";

		$cond.= $wpdb->prepare( "AND h.status = %d ", 1 );

		if( isset( $filters['product_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.product_id = %s ", $filters['product_id'] );
		}
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND h.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['strg_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.strg_id = %s ", $filters['strg_id'] );
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'h.doc_date' => 'ASC', 'd.item_id' => 'ASC' ];
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

	public function get_movement( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
			}
		}

		if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }

        $field = "b.docno, b.doc_id, b.doc_type, b.doc_post_date ";
		$field.= ", w.product_id, w.type, w.plus_sign, w.qty AS bqty, w.unit AS bunit
			, IF( (w.plus_sign='+' AND w.type>0) OR (w.plus_sign='-' AND w.type<0), w.amount, 0 ) AS total_price
			, IF( (w.plus_sign='+' AND w.type>0) OR (w.plus_sign='-' AND w.type<0), w.price, 0 ) AS unit_price 
			, IF( (w.plus_sign='-' AND w.type>0) OR (w.plus_sign='+' AND w.type<0), w.amount, 0 ) AS total_cost
			, IF( (w.plus_sign='-' AND w.type>0) OR (w.plus_sign='+' AND w.type<0), w.price, 0 ) AS unit_cost
			, w.bal_qty, w.bal_unit, w.bal_price, w.bal_amount, w.lupdate_at ";
		
		$table = "{$dbname}{$this->tables['transaction_weighted']} w ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction_items']} a ON a.did = w.did AND a.item_id = w.item_id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";

		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		$cond.= $wpdb->prepare( "AND w.status != %d ", 0 );

		if( isset( $filters['product_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND w.product_id = %s ", $filters['product_id'] );
		}
		if( isset( $filters['warehouse_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND w.warehouse_id = %s ", $filters['warehouse_id'] );
		}
		if( isset( $filters['strg_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND w.strg_id = %s ", $filters['strg_id'] );
		}
		if( isset( $filters['transact'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.plus_sign = %s ", $filters['transact'] );
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
        $order = !empty( $order )? $order : [ 'w.lupdate_at' => 'ASC', 'w.wid' => 'ASC' ];
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

	public function get_last_sale( $prdt_id = 0, $seller = 0 )
	{
		if( ! $prdt_id ) return false;

		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $seller ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $seller )? $seller : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }
		
		$fld = "a.* ";
		$tbl = "{$dbname}{$this->tables['selling_price']} a ";
		$cond = $wpdb->prepare( "AND a.prdt_id = %d AND a.status > %d ", $prdt_id, 0 );
		$ord = "ORDER BY a.sales_date DESC ";
		$l = "LIMIT 0,1 ";
		
		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$ord} {$l}";

		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function get_pos_sales( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) || ( $this->warehouse['view_outlet'] && $this->warehouse['id'] ) )
        {
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }
		
		$field = "DATE_FORMAT( a.post_date, '%Y-%m-%d' ) AS date, i.id, i.code AS item_code, i.name AS item_name, l.meta_value AS uom ";
		$field.= ", SUM( e.meta_value ) AS qty, ROUND( SUM( h.meta_value * e.meta_value ), 3 ) AS weight ";
		$field.= ", ROUND( SUM( h.meta_value * e.meta_value ) / SUM( e.meta_value ), 3 ) AS avg_weight ";
		$field.= ", ROUND( SUM( g.meta_value ) / SUM( e.meta_value ), 5 ) AS avg_price, ROUND( SUM( g.meta_value ), 2 ) AS line_total ";

		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_items']} c ON c.order_id = a.ID AND c.order_item_type = 'line_item' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} d ON d.order_item_id = c.order_item_id AND d.meta_key = '_items_id' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} e ON e.order_item_id = c.order_item_id AND e.meta_key = '_qty' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} g ON g.order_item_id = c.order_item_id AND g.meta_key = '_line_total' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} h ON h.order_item_id = c.order_item_id AND h.meta_key = '_unit' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = c.order_item_id AND l.meta_key = '_uom' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['main']} i ON i.id = d.meta_value ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value IS NOT NULL ", 'shop_order' );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date'] );
		}
		if( isset( $filters['product_id'] ) )
		{
			if( is_array( $filters['product_id'] ) )
				$cond.= "AND i.id IN ('" .implode( "','", $filters['product_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND i.id = %d ", $filters['product_id'] );
		}
		
		$grp = "GROUP BY date, i.code ";
		
		//order
		if( empty( $order ) )
		{
			$order = [ 'date' => 'DESC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}

	/**
	SELECT i.code AS 'Code', i.name AS 'Name'
	, i.category_code AS 'Category Code', i.category_name AS 'Category'
	, i.category_group_code AS 'Category Group Code', i.category_group AS 'Category Group' 
	, i.item_group AS 'Group', i.store_type AS 'Store Type', i.uom AS 'UOM'
	, IF( i.base_code != i.code, i.base_code, '' ) AS 'Base Item Code', IF( i.base_code != i.code, i.base_unit, '' ) AS 'Base Conversion'
	, IF( i.required_metric > 0, 'YES', 'NO' ) AS 'Need Metric (kg/l)'
	, t.total_in AS 'Total In Qty', t.total_out AS 'Total Out Qty', t.balance_qty AS 'Balance Qty'
	, t.cost_in AS 'Stock In Amt', t.cost_out AS 'Stock Out Amt', t.balance_cost AS 'Balance Cost'
	, IF( i.base_id != i.id, ROUND( tt.cost_in / ( tt.total_in / i.base_unit ), 5 ), ROUND( t.cost_in / t.total_in, 5 ) ) AS avg_unit_price
	, @cqty:= IF( i.base_id != i.id, ROUND( tt.balance_qty / i.base_unit, 2 ), '' ) AS 'Converted Qty'
	, IF( i.base_id != i.id, ROUND( @cqty + t.balance_qty, 2 ), '' ) AS 'Converted Bal Qty'
	FROM (
		SELECT a.id, a.code AS code, a.name, CONCAT( grp.code, '-', grp.name ) AS item_group , CONCAT( br.code, '-', br.name ) AS brand
		, cat.slug AS category_code, cat.name AS category_name, pcat.slug AS category_group_code, pcat.name AS category_group, a._uom_code AS uom 
		, CONCAT( st.code, '-', st.name ) AS store_type, group_concat( distinct ta.code order by t.level asc separator ',' ) as breadcrumb_code
		, a.store_type_id, a.status, a.flag
		, ic.base_id, b.code AS base_code, b.name AS base_name, ic.base_unit 
		, IF( rr.id > 0 AND mb.meta_value > 0 AND NOT ( UPPER( a._uom_code ) = 'KG' OR UPPER( a._uom_code ) = 'L' ) , 1, 0 ) AS required_metric 
		FROM wp_stmm_wcwh_items a 
		INNER JOIN wp_stmm_wcwh_items_tree t ON t.descendant = a.id 
		INNER JOIN wp_stmm_wcwh_items ta force index(primary) ON ta.id = t.ancestor 
		LEFT JOIN wp_stmm_wcwh_item_group grp ON grp.id = a.grp_id 
		LEFT JOIN wp_stmm_wcwh_item_store_type st ON st.id = a.store_type_id 
		LEFT JOIN wp_stmm_terms cat ON cat.term_id = a.category 
		LEFT JOIN wp_stmm_term_taxonomy taxo ON taxo.term_id = cat.term_id
		LEFT JOIN wp_stmm_terms pcat On pcat.term_id = taxo.parent
		LEFT JOIN wp_stmm_wcwh_itemsmeta ma ON ma.items_id = a.id AND ma.meta_key = '_brand' 
		LEFT JOIN wp_stmm_wcwh_brand br ON br.code = ma.meta_value 
		LEFT JOIN wp_stmm_wcwh_itemsmeta mb ON mb.items_id = a.id AND mb.meta_key = 'inconsistent_unit' 
		LEFT JOIN wp_stmm_wcwh_reprocess_item rr ON rr.items_id = a.id AND rr.status > 0 
		LEFT JOIN wp_stmm_wcwh_item_converse ic ON ic.item_id = a.id 
		LEFT JOIN wp_stmm_wcwh_items b ON b.id = ic.base_id 
		WHERE 1 
		GROUP BY a.code, a.serial, a.id 
	) i 
	LEFT JOIN (
		{inventory_transaction}
	) t ON t.product_id = i.id 
	LEFT JOIN (
		{inventory_transaction}
	) tt ON tt.product_id = i.base_id 
	WHERE 1 AND i.store_type_id = '1' AND i.status > 0 
	ORDER BY i.base_code ASC , i.breadcrumb_code ASC
	 */
	public function get_export_data( $filters = array(), $single = false )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}
		
		$this->temp_inventory( $filters );

		$fld = "a.id, a.serial AS gtin, a.serial2 AS extra_gtin, a.code AS code, a.name, CONCAT( grp.code, '-', grp.name ) AS item_group, CONCAT( br.code, '-', br.name ) AS brand
			, cat.slug AS category_code, cat.name AS category_name, pcat.slug AS category_group_code, pcat.name AS category_group
			, a._uom_code AS uom, CONCAT( st.code, '-', st.name ) AS store_type
			, group_concat( distinct ta.code order by t.level asc separator ',' ) as breadcrumb_code
			, a.store_type_id, a.status, a.flag, ic.base_id, b.code AS base_code, b.name AS base_name, ic.base_unit 
			, IF( rr.id > 0 AND mb.meta_value > 0 AND NOT ( UPPER( a._uom_code ) = 'KG' OR UPPER( a._uom_code ) = 'L' ) , 1, 0 ) AS required_metric ";

		$tbl = "{$dbname}{$this->tables['main']} a ";
		$tbl.= "INNER JOIN {$dbname}{$this->tables['tree']} t ON t.descendant = a.id ";
		$tbl.= "INNER JOIN {$dbname}{$this->tables['main']} ta force index(primary) ON ta.id = t.ancestor ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_group']} grp ON grp.id = a.grp_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_store_type']} st ON st.id = a.store_type_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} cat ON cat.term_id = a.category ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['taxonomy']} taxo ON taxo.term_id = cat.term_id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} pcat On pcat.term_id = taxo.parent ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['meta']} ma ON ma.items_id = a.id AND ma.meta_key = '_brand' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['brand']} br ON br.code = ma.meta_value ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['meta']} mb ON mb.items_id = a.id AND mb.meta_key = 'inconsistent_unit' ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['reprocess_item']} rr ON rr.items_id = a.id AND rr.status > 0 ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.id ";
		$tbl.= "LEFT JOIN {$dbname}{$this->tables['main']} b ON b.id = ic.base_id  ";

			$subsql = "SELECT ancestor AS id FROM {$dbname}{$this->tables['category_tree']} ";
			$subsql.= "WHERE 1 AND descendant = cat.term_id ORDER BY level DESC LIMIT 0,1 ";
			$tbl.= "LEFT JOIN {$dbname}{$this->tables['category']} ct ON ct.term_id = ( $subsql ) ";

		$cond = "";

		$grp = "GROUP BY a.code, a.serial, a.id ";
		$ord = "";

		if( isset( $filters['store_type_id'] ) )
		{
			if( $filters['store_type_id'] == 'not' )
			{
				$cond.= "AND ( a.store_type_id IS NULL OR a.store_type_id = 0 ) ";
			}
			else
			{
				$cond.= $wpdb->prepare( "AND a.store_type_id = %s ", $filters['store_type_id'] );
			}
		}
		if( isset( $filters['inconsistent_unit'] ) )
		{
			$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", $filters['inconsistent_unit'] );
		}
		if( isset( $filters['item_id'] ) )
		{
            if( is_array( $filters['item_id'] ) )
                $cond.= "AND a.id IN ('" .implode( "','", $filters['item_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a.id = %s ", $filters['item_id'] );
		}
		if( is_array( $filters['category'] ) )
		{
			$catcd = "ct.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
			$catcd.= "OR cat.term_id IN ('" .implode( "','", $filters['category'] ). "') ";
			$cond.= "AND ( {$catcd} ) ";
		}
		if( isset( $filters['grp_id'] ) )
		{
			if( is_array( $filters['grp_id'] ) )
                $cond.= "AND grp.id IN ('" .implode( "','", $filters['grp_id'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND grp.id = %s ", $filters['grp_id'] );
		}
		if( isset( $filters['_brand'] ) )
		{
			if( is_array( $filters['_brand'] ) )
                $cond.= "AND br.code IN ('" .implode( "','", $filters['_brand'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND br.code = %s ", $filters['_brand'] );
		}
		if( isset( $filters['_uom_code'] ) )
		{
			if( is_array( $filters['_uom_code'] ) )
                $cond.= "AND a._uom_code IN ('" .implode( "','", $filters['_uom_code'] ). "') ";
            else
                $cond.= $wpdb->prepare( "AND a._uom_code = %s ", $filters['_uom_code'] );
		}
		
		$item_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

		//------------------------------------------------------

		$field = "i.code AS 'Item Code', i.name AS 'Item Name', i.gtin AS 'Gtin', i.extra_gtin AS 'Extra Gtin'
		, i.category_code AS 'Category Code', i.category_name AS 'Category Name'
		, i.category_group_code AS 'Category Group Code', i.category_group AS 'Category Group Name' 
		, i.item_group AS 'Group', i.store_type AS 'Store Type', i.uom AS 'UOM'
		, IF( i.base_code != i.code, i.base_code, '' ) AS 'Base Item Code', IF( i.base_code != i.code, i.base_unit, '' ) AS 'Base Conversion'
		, IF( i.required_metric > 0, 'YES', 'NO' ) AS 'Need Metric (kg/l)' ";

		$field.= ", t.in_qty AS 'In Qty', t.out_qty AS 'Out Qty', t.pos_qty AS 'POS Qty'
		, t.out_qty + t.pos_qty AS 'Total Out Qty'
		, t.foc_qty AS 'FOC Qty'
		, @bal_qty:= t.in_qty - t.out_qty - t.pos_qty AS 'Balance Qty' 
		, t.in_cost AS 'Stock In Amt', t.out_cost AS 'Stock Out Amt', t.pos_cost AS 'POS Amt'
		, IF( @bal_qty = 0 AND t.in_cost-t.out_cost-t.pos_cost != 0, 0, t.in_cost - t.out_cost - t.pos_cost ) AS 'Balance Cost' 
		, IF( i.base_id != i.id, ROUND( (tt.in_cost-tt.out_cost) / ( (tt.in_qty-tt.out_qty) / i.base_unit ), 5 ), ROUND( (t.in_cost-t.out_cost) / (t.in_qty-t.out_qty), 5 ) ) AS 'Bal Unit Price' 
		, @cqty:= IF( i.base_id != i.id, ROUND( (tt.in_qty-tt.out_qty-tt.pos_qty) / i.base_unit, 2 ), '' ) AS 'Converted Qty' 
		, IF( i.base_id != i.id, ROUND( @cqty + (t.in_qty-t.out_qty-t.pos_qty), 2 ), '' ) AS 'Converted Bal Qty' ";

		if( isset( $filters['pending_gr'] ) && isset( $filters['client_code'] ) )
		{
			$field = "i.code AS 'Item Code', i.name AS 'Item Name', i.gtin AS 'Gtin', i.extra_gtin AS 'Extra Gtin'
			, i.category_code AS 'Category Code', i.category_name AS 'Category Name'
			, i.category_group_code AS 'Category Group Code', i.category_group AS 'Category Group Name' 
			, i.item_group AS 'Group', i.store_type AS 'Store Type', i.uom AS 'UOM'
			, IF( i.base_code != i.code, i.base_code, '' ) AS 'Base Item Code', IF( i.base_code != i.code, i.base_unit, '' ) AS 'Base Conversion'
			, IF( i.required_metric > 0, 'YES', 'NO' ) AS 'Need Metric (kg/l)' ";

			$field.= ", t.in_qty AS 'In Qty', t.out_qty AS 'Out Qty', t.pos_qty AS 'POS Qty'
			, t.out_qty + t.pos_qty AS 'Total Out Qty'
			, @bal_qty:= t.in_qty - t.out_qty - t.pos_qty AS 'Balance Qty' 
			, t.in_cost AS 'Stock In Amt', t.out_cost AS 'Stock Out Amt', t.pos_cost AS 'POS Amt'
			, IF( @bal_qty = 0 AND t.in_cost-t.out_cost-t.pos_cost != 0, 0, t.in_cost - t.out_cost - t.pos_cost ) AS 'Balance Cost' 
			, t.pending_gr AS 'Pending GR Qty', t.pending_gr_amt AS 'Pending GR Amt'
			, t.in_qty - t.out_qty - t.pos_qty + t.pending_gr AS 'Balance Qty After Pending GR' 
			, IF( i.base_id != i.id, ROUND( (tt.in_cost-tt.out_cost) / ( (tt.in_qty-tt.out_qty) / i.base_unit ), 5 ), ROUND( (t.in_cost-t.out_cost) / (t.in_qty-t.out_qty), 5 ) ) AS 'Bal Unit Price' 
			, @cqty:= IF( i.base_id != i.id, ROUND( (tt.in_qty-tt.out_qty-tt.pos_qty) / i.base_unit, 2 ), '' ) AS 'Converted Qty' 
			, IF( i.base_id != i.id, ROUND( @cqty + (t.in_qty-t.out_qty-t.pos_qty), 2 ), '' ) AS 'Converted Bal Qty' ";
		}//, t.in_cost - t.out_cost - t.pos_cost + t.pending_gr_amt AS 'Balance Cost After Pending GR'

		$table = "( {$item_sql} ) i ";
		$table.= "LEFT JOIN {$this->tables['temp_inv']} t ON t.product_id = i.id ";
		$table.= "LEFT JOIN {$this->tables['temp_inv']} tt ON tt.product_id = i.base_id  ";
		
		$cond = "AND i.status > 0 ";
		$grp = "";
		$ord = "ORDER BY i.base_code ASC , i.breadcrumb_code ASC ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		$this->drop_temp_inventory();
		//pd($results);
		return $this->after_get_export_data( $results );
	}

		public function after_get_export_data( $results = [] )
		{
			if( ! $results ) return $results;

			$replaceable_items = apply_filters( 'wcwh_get_item', [ 'is_returnable'=>'1' ], [], false, [ 'meta'=>[ 'is_returnable' ] ] );
			if( ! $replaceable_items ) return $results;

			$r_codes = []; $r_items_code = []; $r_id_code = []; $ngt = [];
			foreach( $replaceable_items as $i => $item )
			{
				$r_codes[] = $item['code'];
				$r_items_code[ $item['code'] ] = $item;
				$r_id_code[ $item['id'] ] = $item['code'];

				if( $item['ref_prdt'] > 0 ) $ngt[] = $item['ref_prdt'];
			}

			$ngt_code = [];
			$ngt = array_unique( $ngt );
			foreach( $ngt as $nt )
			{
				$ngt_code[] = $r_id_code[ $nt ];
			}

			$base_pos_qty = []; $ngt_row_idx = [];
			foreach( $results as $i => $row )
			{
				if( in_array( $row['Item Code'], $r_codes ) )
				{
					if( $r_items_code[ $row['Item Code'] ]['ref_prdt'] > 0 )
					{
						$base_pos_qty[ $r_id_code[ $r_items_code[ $row['Item Code'] ]['ref_prdt'] ] ]+= $row['POS Qty'];
						unset( $results[$i] );
					}

					if( in_array( $row['Item Code'], $ngt_code ) )
					{
						$ngt_row_idx[ $row['Item Code'] ] = $i;
					}
				}
			}

			if( $ngt_row_idx )
			{
				foreach( $ngt_row_idx as $code => $idx )
				{
					$results[ $idx ]['POS Qty']+= $base_pos_qty[ $code ];
					$results[ $idx ]['Total Out Qty']+= $base_pos_qty[ $code ];
					$results[ $idx ]['Balance Qty']-= $base_pos_qty[ $code ];

					$results[ $idx ]['POS Amt'] = round_to( $results[ $idx ]['Stock In Amt'] / $results[ $idx ]['In Qty'] * $base_pos_qty[ $code ], 2 );
					$results[ $idx ]['Balance Cost']-= $results[ $idx ]['POS Amt'];

					if( $results[ $idx ]['Balance Qty After Pending GR'] )
						$results[ $idx ]['Balance Qty After Pending GR']-= $base_pos_qty[ $code ];
				}
			}

			return $results;
		}

		/**
		 * inventory_transaction
		SELECT a.product_id
		, SUM( a.total_in ) AS total_in 
		, SUM( a.total_out ) AS total_out 
		, SUM( a.total_in ) - SUM( a.total_out ) AS balance_qty 
		, SUM( a.total_price ) AS cost_in, SUM( a.total_cost ) AS cost_out, SUM( a.total_price ) - SUM( a.total_cost ) AS balance_cost 
		FROM (
			SELECT a.product_id, SUM( a.total_cost ) AS total_cost 
			SUM( a.total_price ) AS total_price, a.plus_sign
			, SUM( a.bqty ) AS total_in, 0 AS total_out
			FROM wp_stmm_wcwh_transaction_items a
			LEFT JOIN wp_stmm_wcwh_transaction b ON b.hid = a.hid
			WHERE 1 AND a.status != 0 AND b.status != 0 AND a.plus_sign = '+' AND a.strg_id = 1 
			AND b.doc_post_date <= '2022-02-06 23:59:59'
			GROUP BY a.product_id
		UNION ALL
			SELECT a.product_id, SUM( a.total_cost ) AS total_cost 
			, SUM( a.total_price ) AS total_price, a.plus_sign 
			, 0 AS total_in, SUM( a.bqty ) AS total_out
			FROM wp_stmm_wcwh_transaction_items a
			LEFT JOIN wp_stmm_wcwh_transaction b ON b.hid = a.hid
			WHERE 1 AND a.status != 0 AND b.status != 0 AND a.plus_sign = '-' AND a.strg_id = 1 
			AND b.doc_post_date <= '2022-02-06 23:59:59'
			GROUP BY a.product_id
		UNION ALL
			SELECT j.meta_value AS product_id, 0 AS total_cost
			, 0 AS total_price, '-' AS plus_sign
			, 0 AS total_in, SUM( k.meta_value ) AS total_out 
			FROM wp_stmm_posts a 
			LEFT JOIN wp_stmm_postmeta c ON c.post_id = a.ID AND c.meta_key = '_order_total'
			LEFT JOIN wp_stmm_woocommerce_order_items i ON i.order_id = a.ID AND i.order_item_type = 'line_item' 
			LEFT JOIN wp_stmm_woocommerce_order_itemmeta j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' 
			LEFT JOIN wp_stmm_woocommerce_order_itemmeta k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' 
			LEFT JOIN wp_stmm_woocommerce_order_itemmeta l ON l.order_item_id = i.order_item_id AND l.meta_key = '_line_total' 
			LEFT JOIN wp_stmm_woocommerce_order_itemmeta m ON m.order_item_id = i.order_item_id AND m.meta_key = '_unit' 
			WHERE 1 AND a.post_type = 'shop_order' AND a.post_status IN( 'wc-processing', 'wc-completed' ) 
			AND a.post_date <= '2022-02-06 23:59:59'
			AND c.meta_value > 0
			GROUP BY j.meta_value
		) a 
		WHERE 1 
		GROUP BY a.product_id
		 */
		public function temp_inventory( $filters = [] )
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			if( isset( $filters['seller'] ) )
			{
				$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
				$dbname = ( $dbname )? $dbname."." : "";
			}

			if( isset( $filters['strg_id'] ) )
			{
				$strg = apply_filters( 'wcwh_get_storage', [ 'id'=>$filters['strg_id'] ], [], true );
			}

			//-----------------------------------------------------------
				
				$fld = "ic.base_id AS product_id 
					, SUM( a.weighted_total ) AS total_price, 0 AS total_cost 
					, a.plus_sign, SUM( IFNULL(a.bqty,0) * IFNULL(ic.base_unit,1) ) AS total_in, 0 AS total_out 
					, 0 AS pos_qty, 0 AS pending_gr, 0 AS pending_gr_amt
					, SUM( IF( a.bqty > 0 AND a.total_price = 0, a.bqty - a.deduct_qty, 0 ) ) AS foc_qty ";
				
				$tbl = "{$dbname}{$this->tables['transaction_items']} a ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document']} c ON c.doc_id = b.doc_id ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";
				
				$cond = "AND a.status != 0 AND b.status != 0 AND a.plus_sign = '+' ";
				if( isset( $filters['warehouse_id'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
				}
				if( isset( $filters['strg_id'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
				}
				if( isset( $filters['until'] ) )
				{
					$cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['until'] );
				}
				if( isset( $filters['item_id'] ) )
				{
		            if( is_array( $filters['item_id'] ) )
		                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
		            else
		                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
				}

				$grp = "GROUP BY ic.base_id ";
				$ord = "";

				$in_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

			//-----------------------------------------------------------
				
				$fld = "ic.base_id AS product_id 
					, 0 AS total_price, SUM( a.weighted_total ) AS total_cost 
					, a.plus_sign, 0 AS total_in, SUM( IFNULL(a.bqty,0) * IFNULL(ic.base_unit,1) ) AS total_out 
					, 0 AS pos_qty, 0 AS pending_gr, 0 AS pending_gr_amt
					, 0 AS foc_qty ";
				
				$tbl = "{$dbname}{$this->tables['transaction_items']} a ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['transaction']} b ON b.hid = a.hid ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document']} c ON c.doc_id = b.doc_id ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.product_id ";
				
				$cond = "AND a.status != 0 AND b.status != 0 AND a.plus_sign = '-' ";
				if( isset( $filters['warehouse_id'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
				}
				if( isset( $filters['strg_id'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.strg_id = %s ", $filters['strg_id'] );
				}
				if( isset( $filters['until'] ) )
				{
					$cond.= $wpdb->prepare( "AND b.doc_post_date <= %s ", $filters['until'] );
				}
				if( isset( $filters['item_id'] ) )
				{
		            if( is_array( $filters['item_id'] ) )
		                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
		            else
		                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
				}

				$grp = "GROUP BY ic.base_id ";
				$ord = "";

				$out_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

			//-----------------------------------------------------------
				
				$pos_arc = apply_filters( 'wcwh_get_setting', '', '', $filters['seller'], 'wcwh_pos_arc_date' );

				$fld = "ic.base_id AS product_id, 0 AS total_price, 0 AS total_cost 
					, '-' AS plus_sign, 0 AS total_in, 0 AS total_out 
					, SUM( IFNULL(k.meta_value,0) * IFNULL(ic.base_unit,1) ) AS pos_qty
					, 0 AS pending_gr, 0 AS pending_gr_amt
					, 0 AS foc_qty ";
				
				$tbl = "{$dbname}{$this->tables['order']} a ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_meta']} c ON c.post_id = a.ID AND c.meta_key = '_order_total' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_items']} i ON i.order_id = a.ID AND i.order_item_type = 'line_item' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} j ON j.order_item_id = i.order_item_id AND j.meta_key = '_items_id' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} k ON k.order_item_id = i.order_item_id AND k.meta_key = '_qty' ";
				//$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} l ON l.order_item_id = i.order_item_id AND l.meta_key = '_line_total' ";
				//$tbl.= "LEFT JOIN {$dbname}{$this->tables['order_itemmeta']} m ON m.order_item_id = i.order_item_id AND m.meta_key = '_unit' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = j.meta_value ";
				
				$cond = "AND a.post_type = 'shop_order' AND a.post_status IN( 'wc-processing', 'wc-completed' ) AND c.meta_value IS NOT NULL ";
				if( isset( $filters['until'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['until'] );
				}
				if( $pos_arc )
				{
					$arc_date = date( 'Y-m-d 00:00:00', strtotime( $pos_arc." +1 day" ) );
					$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $arc_date );
				}
				if( isset( $filters['item_id'] ) )
				{
		            if( is_array( $filters['item_id'] ) )
		                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
		            else
		                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
				}

				$grp = "GROUP BY ic.base_id ";
				$ord = "";

				$pos_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
				
				if( $pos_arc )
				{
					$fld = "ic.base_id AS product_id, 0 AS total_price, 0 AS total_cost 
						, '-' AS plus_sign, 0 AS total_in, 0 AS total_out 
						, SUM( IFNULL(a.qty,0) * IFNULL(ic.base_unit,1) ) AS pos_qty
						, 0 AS pending_gr, 0 AS pending_gr_amt
						, 0 AS foc_qty ";
						
					$tbl = "{$dbname}{$this->tables['pos_arc']} a ";
					$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = a.prdt_id ";
					
					$cond = "";
					if( isset( $filters['warehouse_id'] ) )
					{
						$cond.= $wpdb->prepare( "AND a.warehouse_id = %s ", $filters['warehouse_id'] );
					}
					if( isset( $filters['item_id'] ) )
					{
						if( is_array( $filters['item_id'] ) )
							$cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
						else
							$cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
					}

					$grp = "GROUP BY ic.base_id ";
					$ord = "";

					$pos_arc_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";
				}

			//-----------------------------------------------------------

				$fld = "ic.base_id AS product_id, 0 AS total_price, 0 AS total_cost 
					, '+' AS plus_sign, 0 AS total_in, 0 AS total_out, 0 AS pos_qty 
					, SUM( ( IFNULL(b.bqty,0) - IFNULL(b.uqty,0) ) * IFNULL(ic.base_unit,1) ) AS pending_gr
					, SUM( IFNULL(b.bqty,0) * mc.meta_value ) AS pending_gr_amt
					, 0 AS foc_qty ";
				
				$tbl = "{$dbname}{$this->tables['document']} a ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} ma ON ma.doc_id = a.doc_id AND ma.item_id = 0 AND ma.meta_key = 'supply_to_seller' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mb ON mb.doc_id = a.doc_id AND mb.item_id = 0 AND mb.meta_key = 'client_company_code' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} mc ON mc.doc_id = a.doc_id AND mc.item_id = b.item_id AND mc.meta_key = 'sprice' ";
				$tbl.= "LEFT JOIN {$dbname}{$this->tables['item_converse']} ic ON ic.item_id = b.product_id ";
				
				$cond = "AND a.doc_type = 'delivery_order' AND a.status = 6 ";

				if( isset( $filters['warehouse_id'] ) )
				{
					$cond.= $wpdb->prepare( "AND ma.meta_value = %s ", $filters['warehouse_id'] );
				}
				if( isset( $filters['client_code'] ) )
				{
					$cond.= $wpdb->prepare( "AND mb.meta_value = %s ", $filters['client_code'] );
				}
				if( isset( $filters['until'] ) )
				{
					$cond.= $wpdb->prepare( "AND a.doc_date <= %s ", $filters['until'] );
				}
				if( isset( $filters['item_id'] ) )
				{
		            if( is_array( $filters['item_id'] ) )
		                $cond.= "AND ic.base_id IN ('" .implode( "','", $filters['item_id'] ). "') ";
		            else
		                $cond.= $wpdb->prepare( "AND ic.base_id = %s ) ", $filters['item_id'] );
				}

				$grp = "GROUP BY ic.base_id ";
				$ord = "";

				$gr_sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} {$grp} {$ord} ";

			//-----------------------------------------------------------

			$field = "a.product_id
				, SUM( a.total_in ) AS in_qty, SUM( a.total_out ) AS out_qty, SUM( a.pos_qty ) AS pos_qty 
				, SUM( a.total_price ) AS in_cost, SUM( a.total_cost ) AS out_cost
				, ROUND( ( SUM(a.total_price)-SUM(a.total_cost) ) / ( SUM(a.total_in)-SUM(a.total_out) ) * SUM( a.pos_qty ), 2 ) AS pos_cost
				, SUM( a.pending_gr ) AS pending_gr, ROUND( SUM( a.pending_gr_amt ), 2 ) AS pending_gr_amt
				, SUM( a.foc_qty ) AS foc_qty ";
			
			if( $strg['sys_reserved'] == 'staging' )
			{
				$arc_union = "";
				if( $pos_arc && $pos_arc_sql ) $arc_union = " UNION ALL ({$pos_arc_sql}) ";
				$table = "( ({$in_sql}) UNION ALL ({$out_sql}) UNION ALL ({$pos_sql}) {$arc_union} ) a ";

				if( isset( $filters['pending_gr'] ) && isset( $filters['client_code'] ) )
				{
					$table = "( ({$in_sql}) UNION ALL ({$out_sql}) UNION ALL ({$pos_sql}) {$arc_union} UNION ALL ({$gr_sql}) ) a ";
				}
			}
			else if( $strg['sys_reserved'] == 'block_staging' )
				$table = "( ({$in_sql}) UNION ALL ({$out_sql}) ) a ";

			$cond = "";
			
			$grp = "GROUP BY a.product_id ";
			$ord = "ORDER BY a.product_id ASC ";

			$select = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

			$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_inv']} ";
			$query.= "AS ( {$select} ) ";

			$query = $wpdb->query( $query );
		}

		public function drop_temp_inventory()
		{
			global $wcwh;
			$wpdb = $this->db_wpdb;
			$prefix = $this->get_prefix();

			$drop = "DROP TEMPORARY TABLE {$this->tables['temp_inv']} ";
        	$succ = $wpdb->query( $drop );
		}
	
} //class

}