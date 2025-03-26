<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Repleaceable_Class" ) ) 
{

class WCWH_Repleaceable_Class extends WCWH_CRUD_Controller 
{
	protected $section_id = "wh_repleaceable";

	protected $tbl = "inventory";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "Repleaceable_Class";

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
			
			"supplier"			=> $prefix."supplier",
			"status"			=> $prefix."status",
			
			"transaction"			=> $prefix."transaction",
			"transaction_items"		=> $prefix."transaction_items",
			"transaction_meta"		=> $prefix."transaction_meta",
			"transaction_out_ref"	=> $prefix."transaction_out_ref",
			"transaction_conversion"=> $prefix."transaction_conversion",
			
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
			"option"			=> "wp_stmm_options",
		);
	}

	public function get_gt_option($filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [])
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

		$field= "*";
		$table.= "{$dbname}{$this->tables['option']} ";
		$cond = "AND option_name IN ( 'gt_total', 'gtd_qty', 'gt_total_prev', 'gt_total_user' ) ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
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
        	$sid = !empty( $filters['seller'] )? $filters['seller'] : $this->warehouse['id'];
            $dbname = get_warehouse_meta( $sid, 'dbname', true );
            $dbname = ( $dbname )? $dbname."." : "";
        }
       

        $field = "";
        $field = "a.id, a.name, a._sku, a.code, a.serial, a._uom_code, a._self_unit, a._parent_unit, a.parent ";
		$field.= ", a.ref_prdt, a.grp_id, a.store_type_id, a.category, a.status, a.flag ";
		$field.= ", i.id AS inv_id, i.warehouse_id AS wh_code, i.strg_id, i.qty, i.unit, i.wa_amt AS total_cost, i.wa_price AS mavg_cost ";
		$field.= ", i.total_sales_amount, i.mavg_sprice, i.total_in, i.total_out ";
		//$field.= ", i.allocated_qty, i.allocated_unit, i.total_sales_qty, i.total_sales_unit ";

		$field.=", IF(ref.id ,sum(refinv.allocated_qty) + i.allocated_qty, i.allocated_qty) AS allocated_qty 
		, IF(ref.id ,sum(refinv.allocated_unit) + i.allocated_unit, i.allocated_unit) AS allocated_unit 
		, IF(ref.id ,sum(refinv.total_sales_qty) + i.total_sales_qty, i.total_sales_qty) AS total_sales_qty 
		, IF(ref.id ,sum(refinv.total_sales_unit) + i.total_sales_unit, i.total_sales_unit) AS total_sales_unit ";

		/*$field.= ",i.id AS inv_id, i.warehouse_id AS wh_code, i.strg_id ";
		$field.= ", IF(ref.id ,sum(refinv.qty) + i.qty, i.qty) AS qty
		, IF(ref.id ,sum(refinv.allocated_qty) + i.allocated_qty, i.allocated_qty) AS allocated_qty
		, IF(ref.id ,sum(refinv.unit) + i.unit, i.unit) AS unit
		, IF(ref.id ,sum(refinv.allocated_unit) + i.allocated_unit, i.allocated_unit) AS allocated_unit ";

		$field.= ", IF(ref.id ,sum(refinv.total_cost) + i.total_cost, i.total_cost) AS total_cost
		, IF(ref.id ,sum(refinv.total_cost) + i.total_cost, i.total_cost) AS total_cost
		, IF(ref.id ,sum(refinv.mavg_cost) + i.mavg_cost, i.mavg_cost) AS mavg_cost
		, IF(ref.id ,sum(refinv.total_sales_qty) + i.total_sales_qty, i.total_sales_qty) AS total_sales_qty
		, IF(ref.id ,sum(refinv.total_sales_unit) + i.total_sales_unit, i.total_sales_unit) AS total_sales_unit
		, IF(ref.id ,sum(refinv.total_sales_amount) + i.total_sales_amount, i.total_sales_amount) AS total_sales_amount
		, IF(ref.id ,sum(refinv.mavg_sprice) + i.mavg_sprice, i.mavg_sprice) AS mavg_sprice 
		, IF(ref.id ,sum(refinv.total_in) + i.total_in, i.total_in) AS total_in 
		, IF(ref.id ,sum(refinv.total_out) + i.total_out, i.total_out) AS total_out ";*/

		$field.= ", mb.meta_value AS returnable_item ";
		$field.= ", group_concat( distinct ret.id order by ret.code asc separator ',' ) as ret_by";
		$field.= ", group_concat( distinct ref.id order by ref.code asc separator ',' ) as ref_by";

		$table = "";
		$table.= "{$dbname}{$this->tables['main']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['inventory']} i ON i.prdt_id = a.id ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['meta']} ma ON ma.items_id = a.id AND ma.meta_key = 'is_returnable' ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['meta']} mb ON mb.items_id = a.id AND mb.meta_key = 'returnable_item' ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['meta']} mc ON mc.meta_value = a.id AND mc.meta_key = 'returnable_item' ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['main']} ret ON ret.id = mc.items_id AND ret.status > 0 ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['main']} ref ON ref.ref_prdt = a.id AND ref.status > 0 ";
        $table.= "LEFT JOIN {$dbname}{$this->tables['inventory']} refinv ON refinv.prdt_id = ref.id ";

        $cond = "";
        $cond.= "AND a.status > 0 ";
        $cond.= "AND ma.meta_value >= 1 ";

        $grp = "GROUP BY a.code, a.serial, a.id ";

		$cgroup = array();

		$isRef = ($args && $args['ref_rep'])? true : false;
		if($isRef)
		{
			$cond.= "AND (ret.id > 0 OR ref.id > 0 ) ";
		}

		$isNotRef = ($args && $args['not_ref'])? true : false;
		if($isNotRef)
		{
			$cond.= "AND ( (ret.id = '' OR ret.id IS NULL) AND (ref.id = '' OR ref.id IS NULL) ) ";
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

		if( isset( $filters['wh_code'] ) )
        {
            $cond.= $wpdb->prepare( " AND i.warehouse_id = %s ", $filters['wh_code'] );
        }
        if( isset( $filters['strg_id'] ) )
        {
            $cond.= $wpdb->prepare( " AND i.strg_id = %s ", $filters['strg_id'] );
        }
		//-----------------------------------------------------------------------------------------------
		if( isset( $filters['item_id'] ) )
		{
			if( is_array( $filters['item_id'] ) )
               $cond.= "AND a.id IN ('" .implode( "','", $filters['item_id'] ). "') ";
            else
               $cond.= $wpdb->prepare( "AND a.id = %d ", $filters['item_id'] );
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

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

			unset( $filters['status'] );
		}

		$corder = array();
		$order = !empty( $order )? $order : [ 'ref.id' => 'DESC', 'ret.id' => 'DESC','a.code' => 'ASC' ];
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

		if( ! $single && ( $args['converse'] ) )
			$results = $this->get_inventory_alters( $results, $filters );
        
        return $results;
	}

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
		}

} //class

}