<?php
if ( !defined('ABSPATH') )
    exit;
	
if ( !class_exists('WCWH_Inst') ) 
{

class WCWH_Inst 
{

	private $plugin_ref;

	private $db_tables = array();
	private $db_tables_sql = array();
	
	public function __construct( $refs = array() )
	{
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		global $wcwh;
		$this->plugin_ref = ( $refs )? $refs : $wcwh->get_plugin_ref();

		$this->set_DB();
    }

    public function __destruct()
    {
    	unset($this->plugin_ref);
    	unset($this->db_tables);
    	unset($this->db_tables_sql);
    }

    /**
	 *	Set DB tables name
	 */
    private function set_DB()
    {
    	$this->db_tables = array(
    		//configurations
			"doc_runningno" => "doc_runningno",
			"stockout_method" => "stockout_method",
			"templates" => "templates",
			"scheme" => "scheme",
			"permission" => "permission",
			"section" => "section",
			"status" => "status",
			//todo
			"todo_arrangement" => "todo_arrangement",
			"todo_action" => "todo_action",
			"todo" => "todo",
			//stage | docuemnt status
			"stage_header" => "stage_header",
			"stage_details" => "stage_details",
    		//company
			"company" => "company",
			"companymeta" => "companymeta",
			"company_tree" => "company_tree",
			//supplier
			"supplier" => "supplier",
			"suppliermeta" => "suppliermeta",
			"supplier_tree" => "supplier_tree",
			//client
			"client" => "client",
			"clientmeta" => "clientmeta",
			"client_tree" => "client_tree",
			//brand
			"brand" => "brand",
			"brandmeta" => "brandmeta",
			"brand_tree" => "brand_tree",
			//general infos
			"addresses" => "addresses",
			"contacts" => "contacts",
    		//warehouse / store
    		"warehouse" => "warehouse",
			"warehousemeta" => "warehousemeta",
			"warehouse_tree" => "warehouse_tree",
			//warehouse's location & storage
			"storage" => "storage",
			"storagemeta" => "storagemeta",
			"storage_tree" => "storage_tree",
			"storage_group_rel" => "storage_group_rel",
			"storage_term_rel" => "storage_term_rel",
			//in / out transaction
			"transaction" => "transaction",
			"transaction_items" => "transaction_items",
			"transaction_weighted" => "transaction_weighted",
			"transaction_conversion" => "transaction_conversion",
			"transaction_meta" => "transaction_meta",
			"transaction_out_ref" => "transaction_out_ref",
			//itemize
			"itemize" => "itemize",
			"itemizemeta" => "itemizemeta",
			//inventory
			"inventory" => "inventory",
			//item / good / products
			"uom" => "uom",
			"uom_conversion" => "uom_conversion",
			"item_reorder_type" => "item_reorder_type",
			"item_group" => "item_group",
			"item_store_type" => "item_store_type",
			"items" => "items",
			"itemsmeta" => "itemsmeta",
			"items_tree" => "items_tree",
			"item_category_tree" => "item_category_tree",
			"item_converse" => "item_converse",
			"item_relation" => "item_relation",
			"item_expiry" => "item_expiry",
			//reprocess item
			"reprocess_item" => "reprocess_item",
			//asset
			"asset" => "asset",
			"assetmeta" => "assetmeta",
			"asset_tree" => "asset_tree",
			"asset_movement" => "asset_movement",
			//price
			"pricing" => "pricing",
			"pricingmeta" => "pricingmeta",
			"price_ref" => "price_ref",
			"price_margin" => "price_margin",
			"price" => "price",
			//pos promo
			"promo_header" => "promo_header",
			"promo_headermeta" => "promo_headermeta",
			"promo_detail" => "promo_detail",
			//documents
			"document" => "document",
			"document_items" => "document_items",
			"document_meta" => "document_meta",
			"document_itemize" => "document_itemize",
			"document_item_tree" => "document_item_tree",
			"document_item_root" => "document_item_root",
			//credit limit
			"credit_term" => "credit_term",
			"credit_limit" => "credit_limit",
			"credit_topup" => "credit_topup",
			"payment_term" => "payment_term",
			//customer
			"customer_group" => "customer_group",
			"customer_group_tree" => "customer_group_tree",
			"customer" => "customer",
			"customer_count" => "customer_count",
			"customermeta" => "customermeta",
			"customer_tree" => "customer_tree",
			"customer_job" => "customer_job",
			"customer_job_tree" => "customer_job_tree",
			"customer_acc_type" => "customer_acc_type",
			"customer_origin" => "customer_origin",
			"customer_origin_tree" => "customer_origin_tree",
			//membership
			"member" => "member",
			"membermeta" => "membermeta",
			"member_transact" => "member_transact",
			"member_transactmeta" => "member_transactmeta",
			//bank in sercice
			"customer_bankin_info" => "customer_bankin_info",
			"exchange_rate" => "exchange_rate",
			"service_charge" => "service_charge",
			//payment method
			"payment_method" => "payment_method",
			//vending machine
			"vending_machine" => "vending_machine",
			//sync & bridge
			"syncing" => "syncing",
			//logs
			"activity_log" => "activity_log",
			"mailing_log" => "mailing_log",
			//others
			"selling_price" => "selling_price",
			"sales" => "sales",
			//stock movement
			"stock_movement" => "stock_movement",
			//margining
			"margining" => "margining",
			"margining_sect" => "margining_sect",
			"margining_det" => "margining_det",
			"margining_sales" => "margining_sales",
    	);
    }

    /**
	 *	Set DB installation query
	 */
    private function set_DB_sql()
    {
    	$this->db_tables_sql = array(
    		//configurations
			"doc_runningno"		//ref_type: default, wh_code, comp_id
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`doc_type` varchar(50) NOT NULL DEFAULT '', 
							`ref_type` varchar(50) DEFAULT 'default', 
							`ref_id` bigint(20) NOT NULL DEFAULT 0, 
							`type` varchar(50) DEFAULT 'default', 
							`length` int(11) NOT NULL DEFAULT 0, 
							`prefix` varchar(50) NOT NULL DEFAULT '', 
							`suffix` varchar(50) NOT NULL DEFAULT '', 
							`next_no` int(11) NOT NULL DEFAULT 1, 
							`status` int(11) NOT NULL DEFAULT 1, 
							PRIMARY KEY (`id`), 
							KEY `doc_type` (`doc_type`), 
							KEY `ref_type` (`ref_type`,`ref_id`,`doc_type`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"stockout_method"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` int(20) NOT NULL AUTO_INCREMENT, 
							`ref_id` bigint(20) NOT NULL DEFAULT 0, 
							`name` varchar(50) NOT NULL DEFAULT '', 
							`order_type` varchar(30) NOT NULL DEFAULT '', 
							`ordering` varchar(4) NOT NULL DEFAULT 'ASC', 
							`priority` smallint NOT NULL DEFAULT 0, 
							`default_value` varchar(20) NULL, 
							PRIMARY KEY (`id`), 
							KEY `ref_id` (`ref_id`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"templates"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`tpl_code` varchar(50) NOT NULL DEFAULT '',
							`tpl_path` varchar(200) NOT NULL DEFAULT '',
							`tpl_file` varchar(100) NOT NULL DEFAULT '',
							`remarks` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`from_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`to_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `tpl_code` (`tpl_code`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"scheme"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`section` varchar(50) NOT NULL DEFAULT '', 
							`section_id` varchar(50) NOT NULL DEFAULT '',
							`scheme` varchar(50) NOT NULL DEFAULT '', 
							`title` varchar(50) NOT NULL DEFAULT '', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0, 
							PRIMARY KEY (`id`), 
							KEY `section` (`section`), 
							KEY `scheme` (`scheme`,`section`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
						
			"permission"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`scheme` varchar(50) NOT NULL DEFAULT 'role', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0,
							`ref_id` varchar(50) NOT NULL DEFAULT 0, 
							`permission` text DEFAULT '',
							PRIMARY KEY (`id`), 
							KEY `permission` (`scheme`,`scheme_lvl`,`ref_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"section"
    				=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`section_id` varchar(50) NOT NULL DEFAULT '', 
							`table` varchar(50) NOT NULL DEFAULT '', 
							`table_key` varchar(30) NOT NULL DEFAULT '',
							`desc` varchar(255) NOT NULL DEFAULT '',
							`push_service` int(3) NOT NULL DEFAULT 0,
							`action_types` text DEFAULT '',
							PRIMARY KEY (`id`), 
							KEY `section_id` (`section_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"status"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`status` int(3) NOT NULL DEFAULT 0, 
							`type` varchar(50) NOT NULL DEFAULT 'default',
							`key` varchar(50) NOT NULL DEFAULT '',
							`title` varchar(50) NOT NULL DEFAULT '',
							`order` int(3) NOT NULL DEFAULT 1, 
							PRIMARY KEY (`id`), 
							KEY `type` (`type`, `status`),
							KEY `key` (`key`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
			
			//todo		
			"todo_arrangement"		//section: wh_company, wh_warehouse, wh_document, ...	
									//action_type: approval, verify, ...
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'arr_id', 
							`section` varchar(50) NOT NULL DEFAULT '', 
							`match_status` int(3) NOT NULL DEFAULT 0,
							`match_proceed` int(3) NOT NULL DEFAULT 0,
							`match_halt` int(3) NOT NULL DEFAULT 0,
							`action_type` varchar(50) NOT NULL DEFAULT '',
							`title` varchar(255) NOT NULL DEFAULT '',
							`desc` text DEFAULT '',
							`order` int(3) NOT NULL DEFAULT 1,
							`status` int(3) NOT NULL DEFAULT 1, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `section` (`section`,`status`), 
							KEY `action_type` (`action_type`,`status`), 
							KEY `matching` (`section`,`match_status`,`match_proceed`,`match_halt`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"todo_action"		//responsible: role, user, ...
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'action_id', 
							`arr_id` int(11) NOT NULL DEFAULT 0, 
							`next_action` varchar(255) DEFAULT '',
							`responsible` varchar(255) DEFAULT '',
							`trigger_action` text DEFAULT '',
							PRIMARY KEY (`id`), 
							KEY `arr_id` (`arr_id`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"todo"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`arr_id` int(11) NOT NULL DEFAULT 0, 
							`ref_id` bigint(20) NOT NULL DEFAULT 0, 
							`docno`	varchar(30) NOT NULL DEFAULT '',
							`doc_title` varchar(250) NOT NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`remark` text DEFAULT '',
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`action_taken` bigint(20) NOT NULL DEFAULT 0,
							`action_by` int(11) NOT NULL DEFAULT 0, 
							`action_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `arr_id` (`arr_id`), 
							KEY `ref_id` (`ref_id`,`arr_id`,`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
			
			//stage | docuemnt status
			"stage_header"		//ref_type = section: wh_company, wh_warehouse...	
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'stage_id', 
							`ref_type` varchar(50) NOT NULL DEFAULT '', 
							`ref_id` bigint(20) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 1, 
							`proceed_status` int(3) NOT NULL DEFAULT 0, 
							`halt_status` int(3) NOT NULL DEFAULT 0, 
							`latest_stage` bigint(20) NOT NULL DEFAULT 0, 
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `ref_type` (`ref_type`,`ref_id`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
					
			"stage_details"		//action: remove, restore, delete, confirm, recall, approve, reject, on-hold, post, unpost, complete, close
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`stage_id` bigint(20) NOT NULL DEFAULT 0, 
							`action` varchar(50) NOT NULL DEFAULT '', 
							`status` int(3) NOT NULL DEFAULT 0, 
							`remark` text DEFAULT '', 
							`metas` longtext DEFAULT '',
							`action_by` int(11) NOT NULL DEFAULT 0, 
							`action_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `stage_id` (`stage_id`), 
							KEY `action` (`action`,`stage_id`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
			
			//company
    		"company"
    				=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'comp_id', 
							`custno` varchar(20) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`regno` varchar(15) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`logo` bigint(20) NOT NULL DEFAULT 0, 
							`credit_term` int(11) NOT NULL DEFAULT 0 COMMENT 'term_id', 
							`credit_limit` decimal(11,2) NOT NULL DEFAULT 0.00, 
							`parent` bigint(20) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 1, 
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `custno` (`custno`,`status`), 
							KEY `code` (`code`,`status`), 
							KEY `parent` (`parent`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"companymeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`company_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `company_id` (`company_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"company_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
			
			//supplier
			"supplier"
    				=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'supplier_id', 
							`comp_id` bigint(20) NOT NULL DEFAULT 0,
							`supplier_no` varchar(20) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`parent` bigint(20) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 1, 
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `supplier_no` (`supplier_no`,`status`), 
							KEY `code` (`code`,`status`), 
							KEY `parent` (`parent`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"suppliermeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`supplier_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `supplier_id` (`supplier_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"supplier_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			//client
			"client"
    				=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'client_id', 
							`comp_id` bigint(20) NOT NULL DEFAULT 0,
							`client_no` varchar(20) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`parent` bigint(20) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 1, 
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `client_no` (`client_no`,`status`), 
							KEY `code` (`code`,`status`), 
							KEY `parent` (`parent`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"clientmeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`client_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `client_id` (`client_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"client_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			//brand
			"brand"
    				=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'brand_id', 
							`comp_id` bigint(20) NOT NULL DEFAULT 0,
							`brand_no` varchar(20) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`parent` bigint(20) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 1, 
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `brand_no` (`brand_no`,`status`), 
							KEY `code` (`code`,`status`), 
							KEY `parent` (`parent`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"brandmeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`brand_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `brand_id` (`brand_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"brand_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
			
			//general infos
			"addresses"		//ref_type: company, customer | addr_type: billing, shipping
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`ref_type` varchar(50) DEFAULT '',
							`ref_id` bigint(20) NOT NULL DEFAULT 0,
							`addr_type` varchar(50) DEFAULT '',
							`address_1` varchar(200) DEFAULT NULL,
							`address_2` varchar(200) DEFAULT NULL,
							`country` varchar(30) DEFAULT '',
							`state` varchar(30) DEFAULT '',
							`city` varchar(50) DEFAULT NULL,
							`postcode` varchar(20) NOT NULL,
							`contact_person` varchar(200) DEFAULT NULL,
							`contact_no` varchar(20) DEFAULT NULL,
							`status` int(3) DEFAULT 1,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`), 
							KEY `ref_id` (`ref_type`,`addr_type`,`ref_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"contacts"		//ref_type: company, warehouse
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`ref_type` varchar(50) NOT NULL DEFAULT '',
							`ref_id` bigint(20) NOT NULL DEFAULT 0 ,
							`name` varchar(200) DEFAULT NULL,
							`phone_no` varchar(20) DEFAULT NULL,
							`fax_no` varchar(20) DEFAULT NULL,
							`email` varchar(200) DEFAULT NULL,
							`status` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`), 
							KEY `ref_id` (`ref_type`,`ref_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
			
			//warehouse / store
			"warehouse"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`code` varchar(20) NOT NULL DEFAULT '' COMMENT 'wh_code', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`comp_id` bigint(20) NOT NULL DEFAULT 0,
							`capability` text DEFAULT '',
							`indication` int(3) NOT NULL DEFAULT 0, 
							`visible` int(3) NOT NULL DEFAULT 1,
							`parent` bigint(20) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 0,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							UNIQUE KEY `code` (`code`), 
							KEY `parent` (`parent`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"warehousemeta"	
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`warehouse_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `warehouse_id` (`warehouse_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"warehouse_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"storage"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'strg_id', 
							`wh_code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '', 
							`parent` bigint(20) NOT NULL DEFAULT 0,
							`sys_reserved` varchar(50) NOT NULL DEFAULT '',
							`storable` int(3) NOT NULL DEFAULT 0, 
							`single_sku` int(3) NOT NULL DEFAULT 0,
							`stackable` int(3) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 0, 
							`flag` int(3) NOT NULL DEFAULT 0,
							`occupied` bigint(20) NOT NULL DEFAULT 0 COMMENT 'did',
							`max_qty` int(11) NOT NULL DEFAULT 0,
							`depth` decimal(11,2) NOT NULL DEFAULT 0.00,
							`width` decimal(11,2) NOT NULL DEFAULT 0.00,
							`height` decimal(11,2) NOT NULL DEFAULT 0.00,
							`capacity` decimal(11,2) NOT NULL DEFAULT 0.00,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `code` (`code`,`wh_code`,`status`),
							KEY `parent` (`parent`,`status`),
							KEY `storable` (`storable`,`wh_code`,`status`,`occupied`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"storagemeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`storage_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `storage_id` (`storage_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"storage_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"storage_group_rel"	//scheme: default > store_type > category > item
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`strg_id` bigint(20) NOT NULL DEFAULT 0, 
							`scheme` varchar(50) NOT NULL DEFAULT 'default', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0,
							`ref_id` varchar(50) NOT NULL DEFAULT '0', 
							PRIMARY KEY (`id`), 
							KEY `strg_id` (`strg_id`),
							KEY `storage_group` (`strg_id`,`scheme`,`scheme_lvl`,`ref_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"storage_term_rel"	//scheme: default > category > item
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`scheme` varchar(50) NOT NULL DEFAULT 'default', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0,
							`ref_id` varchar(50) NOT NULL DEFAULT '', 
							`days` int(3) NOT NULL DEFAULT 0,
							PRIMARY KEY (`id`), 
							KEY `storage_term` (`scheme`,`scheme_lvl`,`ref_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"transaction"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`hid` bigint(20) NOT NULL AUTO_INCREMENT,
							`docno` varchar(30) NOT NULL DEFAULT '',
							`doc_id` bigint(20) NOT NULL DEFAULT 0,
							`doc_type` varchar(50) NOT NULL DEFAULT '',
							`doc_post_date` datetime NOT NULL,
							`plus_sign` varchar(1) NOT NULL,
							`status` int(3) NOT NULL DEFAULT 1,
							`lupdate_by` int(11) DEFAULT NULL, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`hid`),
							KEY `name` (`doc_post_date`,`doc_type`,`doc_id`),
							KEY `doc_id` (`doc_type`,`doc_id`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"transaction_items"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`did` bigint(20) NOT NULL AUTO_INCREMENT,
							`hid` bigint(20) NOT NULL,
							`item_id` bigint(20) NOT NULL DEFAULT 0,
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '', 
							`strg_id` bigint(20) NOT NULL DEFAULT 0, 
							`batch` varchar(30) NOT NULL DEFAULT '',
							`bqty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`bunit` decimal(20,3) NOT NULL DEFAULT 0.000,
							`unit_cost` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`total_cost` decimal(20,2) NOT NULL DEFAULT 0.00,
							`unit_price` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`total_price` decimal(20,2) NOT NULL DEFAULT 0.00,
							`weighted_price` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`weighted_total` decimal(20,2) NOT NULL DEFAULT 0.00,
							`plus_sign` varchar(1) NOT NULL DEFAULT '',
							`deduct_qty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`deduct_unit` decimal(20,3) NOT NULL DEFAULT 0.000,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`lupdate_by` int(11) DEFAULT NULL, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`did`),
							KEY `product_storage` (`product_id`,`warehouse_id`,`strg_id`,`plus_sign`,`flag`), 
							KEY `hid` (`hid`,`warehouse_id`,`strg_id`,`product_id`), 
							KEY `batch` (`batch`,`product_id`,`status`), 
							KEY `item_id` (`item_id`,`status`, `product_id`),
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"transaction_weighted"	//type: 1=posted, -1=unposted
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`wid` bigint(20) NOT NULL AUTO_INCREMENT,
							`did` bigint(20) NOT NULL DEFAULT 0,
							`item_id` bigint(20) NOT NULL DEFAULT 0,
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '', 
							`strg_id` bigint(20) NOT NULL DEFAULT 0, 
							`qty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`unit` decimal(20,3) NOT NULL DEFAULT 0.000,
							`price` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`amount` decimal(20,2) NOT NULL DEFAULT 0.00,
							`type` varchar(1) NOT NULL DEFAULT '1',
							`plus_sign` varchar(1) NOT NULL DEFAULT '',
							`bal_qty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`bal_unit` decimal(20,3) NOT NULL DEFAULT 0.000,
							`bal_price` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`bal_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							`lupdate_by` int(11) DEFAULT NULL, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`wid`),
							KEY `did` (`did`,`item_id`,`status`,`type`), 
							KEY `plus_sign` (`did`,`item_id`,`status`,`type`,`plus_sign`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"transaction_conversion"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`cid` bigint(20) NOT NULL AUTO_INCREMENT,
							`hid` bigint(20) NOT NULL,
							`item_id` bigint(20) NOT NULL DEFAULT 0,
							`from_prdt_id` bigint(20) NOT NULL DEFAULT 0,
							`to_prdt_id` bigint(20) NOT NULL DEFAULT 0,
							`from_qty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`to_qty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`uprice` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`total_price` decimal(20,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							`lupdate_by` int(11) DEFAULT NULL, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`cid`),
							KEY `hid` (`hid`,`item_id`,`from_prdt_id`), 
							KEY `to_prdt_id` (`hid`,`item_id`,`to_prdt_id`), 
							KEY `item_id` (`item_id`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"transaction_meta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) NOT NULL AUTO_INCREMENT,
							`hid` bigint(20) NOT NULL,
							`did` bigint(20) NOT NULL DEFAULT 0,
							`ddid` bigint(20) NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `did` (`did`,`hid`,`ddid`),
							KEY `hid` (`hid`,`did`,`ddid`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"transaction_out_ref"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ddid` bigint(20) NOT NULL AUTO_INCREMENT,
							`hid` bigint(20) NOT NULL,
							`did` bigint(20) NOT NULL,
							`bqty` decimal(20,2) NOT NULL DEFAULT 0.00,
							`bunit` decimal(20,3) NOT NULL DEFAULT 0.000,
							`unit_cost` decimal(20,5) NOT NULL DEFAULT 0.00000,
							`ref_hid` bigint(20) NOT NULL DEFAULT 0,
							`ref_did` bigint(20) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ddid`),
							KEY `product_storage` (`did`,`hid`,`ref_hid`,`ref_did`),
							KEY `ref` (`ref_did`,`ref_hid`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"itemize"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`name` varchar(250) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '',
							`in_did` bigint(20) NOT NULL DEFAULT 0,
							`out_did` bigint(20) NOT NULL DEFAULT 0,
							`sales_item_id` bigint(20) NOT NULL DEFAULT 0,
							`bunit` decimal(11,3) NOT NULL DEFAULT 0.000,
							`unit_cost` decimal(11,5) NOT NULL DEFAULT 0.00000,
							`unit_price` decimal(11,5) NOT NULL DEFAULT 0.00000,
							`expiry` varchar(50) NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`stock` int(3) NOT NULL DEFAULT 1,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `product_id` (`product_id`,`expiry`,`status`,`stock`),
							KEY `in_did` (`in_did`,`product_id`),
							KEY `out_did` (`out_did`,`product_id`),
							KEY `sales_item_id` (`sales_item_id`,`product_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"itemizemeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`itemize_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `itemize_id` (`itemize_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"inventory"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '',
							`strg_id` bigint(20) NOT NULL DEFAULT 0,
							`prdt_id` bigint(20) NOT NULL DEFAULT 0,
							`qty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`reserved_qty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`reserved_unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`allocated_qty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`allocated_unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`total_cost` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`mavg_cost` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`total_sales_qty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`total_sales_unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`total_sales_amount` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`mavg_sprice` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`total_in` decimal(20,2) NOT NULL DEFAULT '0.00',
							`total_in_unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`total_in_cost` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`total_in_avg` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`latest_in_item` bigint(20) NOT NULL DEFAULT 0,
							`latest_in_cost` decimal(20,5) NOT NULL DEFAULT '0.00000', 
							`total_out` decimal(20,2) NOT NULL DEFAULT '0.00',
							`total_out_unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`total_out_cost` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`total_out_avg` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`wa_qty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`wa_unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`wa_amt` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`wa_price` decimal(20,5) NOT NULL DEFAULT '0.00000',
							PRIMARY KEY (`id`),
							KEY `warehouse_id` (`warehouse_id`,`strg_id`,`prdt_id`),
							KEY `prdt_id` (`prdt_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"uom"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT '_uom_id', 
							`code` varchar(10) NOT NULL DEFAULT '' COMMENT '_uom_code', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`fraction` int(3) NOT NULL DEFAULT 0, 
							PRIMARY KEY (`id`), 
							KEY `code` (`code`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"uom_conversion"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`from_uom` varchar(10) NOT NULL DEFAULT '',
							`to_uom` varchar(10) NOT NULL DEFAULT '',
							`from_unit` decimal(10,3) NOT NULL DEFAULT 0.000, 
							`to_unit` decimal(10,3) NOT NULL DEFAULT 0.000, 
							PRIMARY KEY (`id`), 
							KEY `uom_converse` (`from_uom`,`to_uom`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"item_reorder_type"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`wh_code` varchar(50) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '', 
							`lead_time` int(11) NOT NULL DEFAULT 0,
							`order_period` int(11) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `wh_code` (`wh_code`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"item_group"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'grp_id', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`prefix` varchar(20) NOT NULL DEFAULT '',
							`name` varchar(250) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '', 
							PRIMARY KEY (`id`), 
							KEY `code` (`code`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"item_store_type"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'store_type_id', 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '', 
							PRIMARY KEY (`id`), 
							KEY `code` (`code`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"items"			//product_type: simple, virtual
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'items_id',
							`name` varchar(250) NOT NULL DEFAULT '', 
							`_sku` varchar(50) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '', 
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`serial2` text DEFAULT '', 
							`product_type` varchar(50) NOT NULL DEFAULT 'simple',
							`desc` text DEFAULT '',
							`_material` varchar(50) NOT NULL DEFAULT '',
							`_stock_out_type` int(11) NOT NULL DEFAULT 2,
							`_uom_code` varchar(10) NOT NULL DEFAULT '',
							`_self_unit` decimal(11,2) NOT NULL DEFAULT 0.00,
							`_content_uom` varchar(10) NOT NULL DEFAULT '',
							`_parent_unit` decimal(11,2) NOT NULL DEFAULT 0.00,
							`_tax_status` varchar(50) NOT NULL DEFAULT 'none',
							`_tax_class` varchar(50) NOT NULL DEFAULT '',
							`_manage_stock` varchar(30) NOT NULL DEFAULT 'yes',
							`_backorders` varchar(30) NOT NULL DEFAULT 'yes',
							`grp_id` int(11) NOT NULL DEFAULT 0,
							`store_type_id` int(11) NOT NULL DEFAULT 0,
							`category` int(11) NOT NULL DEFAULT 0,
							`parent` bigint(20) NOT NULL DEFAULT 0,
							`ref_prdt` bigint(20) NOT NULL DEFAULT 0,
							`_thumbnail_id` bigint(20) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `_sku` (`_sku`,`status`),
							KEY `parent` (`parent`,`status`),
							KEY `barcode` (`code`,`serial`,`status`), 
							KEY `stock` (`stock`,`status`,`created_at`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"itemsmeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`items_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `items_id` (`items_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"items_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
						
			"item_category_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"item_converse"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`item_id` bigint(20) NOT NULL DEFAULT 0,
							`base_id` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 0,
							`converse` decimal(10,2) NOT NULL DEFAULT 0,
							`base_unit` decimal(10,2) NOT NULL DEFAULT 0,
							PRIMARY KEY (`item_id`),
							KEY `base_id` (`base_id`,`item_id`),
							KEY `level` (`item_id`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"item_relation"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`items_id` bigint(20) NOT NULL DEFAULT 0, 
							`wh_id` varchar(20) NOT NULL DEFAULT '', 
							`rel_type` varchar(100) NOT NULL DEFAULT '',
							`sellable` int(3) NOT NULL DEFAULT 1,
							`alert_level` int(11) NOT NULL DEFAULT 0,
							`order_level` int(11) NOT NULL DEFAULT 0,
							`expiration_days` int(11) NOT NULL DEFAULT 0,
							`reorder_type` int(11) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `reorder_type` (`reorder_type`,`items_id`,'wh_id',`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
						
			// Item Expiry
			"item_expiry"	
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`scheme` varchar(50) NOT NULL DEFAULT 'default', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0,
							`ref_id` varchar(50) NOT NULL DEFAULT '0', 
							`effective_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`shelf_life` varchar(50) DEFAULT NULL,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`),
							KEY `term_scheme` (`scheme`,`scheme_lvl`,`ref_id`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"reprocess_item"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`items_id` bigint(20) NOT NULL DEFAULT 0, 
							`required_item` bigint(20) NOT NULL DEFAULT 0,
							`required_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
							`desc` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `items_id` (`items_id`,`status`), 
							KEY `required_item` (`items_id`,`required_item`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"asset"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'asset_id', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '' COMMENT 'asset_code', 
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`type` varchar(50) NOT NULL DEFAULT '',
							`category` int(11) NOT NULL DEFAULT 0,
							`parent` bigint(20) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 0,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `code` (`code`,`status`), 
							KEY `serial` (`serial`,`status`),
							KEY `parent` (`parent`,`status`),
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
					
			"assetmeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`asset_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `asset_id` (`asset_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"asset_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
						
			"asset_movement"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`code` varchar(20) NOT NULL DEFAULT '',
							`asset_id` bigint(20) NOT NULL DEFAULT 0,
							`asset_no` varchar(50) NOT NULL DEFAULT '',
							`location_code` varchar(20) NOT NULL, 
							`post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`end_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`status` int(3) NOT NULL DEFAULT 1,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) DEFAULT NULL, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `code` (`code`,`status`), 
							KEY `location_code` (`location_code`,`asset_no`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"pricing" 		//scheme: default > wh_code > customer_group 
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'price_id',
							`docno` varchar(30) NOT NULL DEFAULT '', 
							`sdocno` varchar(30) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`code` varchar(20) NOT NULL DEFAULT '' COMMENT 'price_code', 
							`type` varchar(50) NOT NULL DEFAULT '',
							`remarks` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`since` varchar(50) NOT NULL DEFAULT '', 
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `since` (`since`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"pricingmeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`pricing_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `pricing_id` (`pricing_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"price_ref"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`price_id` bigint(20) NOT NULL DEFAULT 0, 
							`seller` varchar(50) NOT NULL DEFAULT '',
							`scheme` varchar(50) NOT NULL DEFAULT 'default', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0,
							`ref_id` varchar(50) NOT NULL DEFAULT '', 
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `price_id` (`price_id`,`status`), 
							KEY `scheme` (`price_id`,`seller`,`scheme`,`scheme_lvl`,`ref_id`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"price_margin" 	//price_type: latest_cost, avg_cost, default
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`price_id` bigint(20) NOT NULL DEFAULT 0, 
							`product_id` bigint(20) NOT NULL,
							`price_type` varchar(50) NOT NULL DEFAULT '',
							`price_value` decimal(20,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `price_id` (`price_id`,`price_type`,`status`), 
							KEY `product_id` (`product_id`,`price_type`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"price" 
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`price_id` bigint(20) NOT NULL DEFAULT 0, 
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`unit_price` decimal(20,2) NOT NULL DEFAULT 0.00,
							`ref_id` bigint(20) NOT NULL DEFAULT 0,
							`factor` decimal(10,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `price_id` (`price_id`,`status`), 
							KEY `product_id` (`product_id`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"promo_header"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'promo_id',
							`docno` varchar(30) NOT NULL DEFAULT '', 
							`sdocno` varchar(30) NOT NULL DEFAULT '', 
							`seller` varchar(50) NOT NULL DEFAULT '',
							`title` varchar(250) NOT NULL DEFAULT '', 
							`remarks` text DEFAULT '',
							`cond_type` varchar(50) NOT NULL DEFAULT '',
							`from_date` varchar(50) NOT NULL DEFAULT '', 
							`to_date` varchar(50) NOT NULL DEFAULT '',
							`limit` int(11) NOT NULL DEFAULT 0, 
							`used` int(11) NOT NULL DEFAULT 0, 
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `docno` (`docno`,`seller`,`status`,`flag`), 
							KEY `availability` (`from_date`,`to_date`,`limit`,`used`,`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"promo_headermeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`promo_header_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `promo_header_id` (`promo_header_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"promo_detail"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`promo_id` bigint(20) NOT NULL DEFAULT 0, 
							`type` varchar(50) NOT NULL DEFAULT '',
							`match` varchar(50) NOT NULL DEFAULT '',
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`amount` decimal(20,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `promo_id` (`promo_id`,`status`), 
							KEY `type` (`promo_id`,`type`,`match`,`status`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"document"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`doc_id` bigint(20) NOT NULL AUTO_INCREMENT, 
							`warehouse_id` varchar(20) NOT NULL DEFAULT '', 
							`docno` varchar(30) NOT NULL DEFAULT '', 
							`sdocno` varchar(30) NOT NULL DEFAULT '', 
							`doc_date` datetime NOT NULL, 
							`post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`doc_type` varchar(30) NOT NULL DEFAULT '', 
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`parent` bigint(20) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`doc_id`), 
							KEY `doc_type` (`doc_type`,`status`,`doc_date`), 
							KEY `warehouse_id` (`warehouse_id`,`doc_type`,`status`), 
							KEY `parent` (`parent`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"document_items"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`item_id` bigint(20) NOT NULL AUTO_INCREMENT,
							`doc_id` bigint(20) NOT NULL,
							`strg_id` bigint(20) NOT NULL DEFAULT 0,
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`uom_id` varchar(20) NOT NULL DEFAULT '',
							`bqty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`uqty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`bunit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`uunit` decimal(20,3) NOT NULL DEFAULT '0.000',
							`ref_doc_id` int NOT NULL DEFAULT 0, 
							`ref_item_id` int NOT NULL DEFAULT 0, 
							`status` int(11) NOT NULL,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`item_id`),
							KEY `doc_id` (`doc_id`,`product_id`,`ref_item_id`,`ref_doc_id`),
							KEY `strg_id` (`doc_id`,`strg_id`,`product_id`),
							KEY `ref_item_id` (`ref_item_id`,`ref_doc_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"document_meta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (  
							`meta_id` bigint(20) NOT NULL AUTO_INCREMENT,  
							`doc_id` bigint(20) NOT NULL,  
							`item_id` bigint(20) NOT NULL DEFAULT 0, 
							`meta_key` varchar(255) DEFAULT NULL,  
							`meta_value` longtext DEFAULT NULL,  
							PRIMARY KEY (`meta_id`),  
							KEY `doc_id` (`doc_id`,`item_id`,`meta_key`(151))
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"document_itemize"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`item_id` bigint(20) NOT NULL,
							`product_id` bigint(20) NOT NULL DEFAULT 0,
							`_sku` varchar(50) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '', 
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '',
							`bunit` decimal(11,3) NOT NULL DEFAULT 0.000,
							`unit_cost` decimal(11,5) NOT NULL DEFAULT 0.00000,
							`unit_price` decimal(11,5) NOT NULL DEFAULT 0.00000,
							`expiry` varchar(50),
							`status` int(3) NOT NULL DEFAULT 1,
							`metas` text DEFAULT '',
							PRIMARY KEY (`id`),
							KEY `item_id` (`item_id`),
							KEY `code` (`code`,`product_id`,`serial`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"document_item_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"document_item_root"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"credit_term"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'term_id',
							`name` varchar(200) NOT NULL DEFAULT '',
							`days` int(3) NOT NULL DEFAULT 0,
							`offset` int(3) NOT NULL DEFAULT 0,
							`type` varchar(50) NOT NULL DEFAULT 'reset',
							`parent` int(11) NOT NULL DEFAULT 0,
							`apply_date` varchar(20) NOT NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`wage_type` varchar(50) NOT NULL DEFAULT '',
							PRIMARY KEY (`id`), 
							KEY `apply_date` (`parent`,`apply_date`,`status`),
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"credit_limit"	//scheme: default > customer_group > customer
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`term_id` int(11) NOT NULL,
							`scheme` varchar(50) NOT NULL DEFAULT 'default', 
							`scheme_lvl` int(3) NOT NULL DEFAULT 0,
							`ref_id` varchar(50) NOT NULL DEFAULT '0', 
							`credit_limit` decimal(11,2) NOT NULL DEFAULT 0.00,
							`percentage` decimal(11,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`),
							KEY `term_id` (`term_id`,`status`),
							KEY `term_scheme` (`term_id`,`scheme`,`scheme_lvl`,`ref_id`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"credit_topup"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`customer_id` int(11) NOT NULL,
							`customer_code` varchar(20) NOT NULL DEFAULT '',
							`sapuid` varchar(20) NOT NULL DEFAULT '',
							`credit_limit` decimal(11,2) NOT NULL DEFAULT 0.00,
							`percentage` decimal(11,2) NOT NULL DEFAULT 0.00,
							`effective_from` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`effective_to` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`),
							KEY `customer_id` (`customer_id`,`status`),
							KEY `customer_code` (`customer_code`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"payment_term"	//type: no, day, month,
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'term_id',
							`name` varchar(200) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '',
							`type` varchar(50) NOT NULL DEFAULT '',
							`days` int(3) NOT NULL DEFAULT 0,
							`creditability` int(3) NOT NULL DEFAULT 0,
							`desc` text DEFAULT '',
							`penalty_factor` decimal(11,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							PRIMARY KEY (`id`), 
							KEY `code` (`code`,`type`,`status`),
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"customer_group"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'cgroup_id',
							`name` varchar(250) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '',
							`parent` int(11) NOT NULL DEFAULT 0,
							`topup_percent` decimal(3,2) NOT NULL DEFAULT '0.00',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							PRIMARY KEY (`id`),
							KEY `code` (`code`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"customer_group_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"customer"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`wh_code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '',
							`uid` varchar(20) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '', 
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`acc_type` int(11) NOT NULL DEFAULT 0,
							`origin` int(11) NOT NULL DEFAULT 0,
							`cjob_id` int(11) NOT NULL DEFAULT 0,
							`cgroup_id` int(11) NOT NULL DEFAULT 0,
							`comp_id` bigint(20) NOT NULL DEFAULT 0,
							`email` varchar(100) NOT NULL DEFAULT '',
							`phone_no` varchar(20) DEFAULT NULL,
							`parent` bigint(20) NOT NULL DEFAULT 0, 
							`auto_topup` int(3) NOT NULL DEFAULT 0,
							`topup_percent` decimal(3,2) NOT NULL DEFAULT '0.00',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `uid` (`uid`,`wh_code`,`status`),
							KEY `serial` (`serial`,`wh_code`,`status`), 
							KEY `cgroup_id` (`cgroup_id`,`wh_code`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"customermeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`customer_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `customer_id` (`customer_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"customer_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"customer_count"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`cust_id` bigint(20) NOT NULL DEFAULT 0,
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`transaction` int(7) NOT NULL DEFAULT 0,
							`purchase` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`credit` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `cust_id` (`cust_id`,`serial`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"customer_job"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'cjob_id',
							`name` varchar(250) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '',
							`parent` int(11) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							PRIMARY KEY (`id`),
							KEY `code` (`code`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
			"customer_job_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
						
			"customer_acc_type"
					=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT, 
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`employee_prefix` varchar(50) NOT NULL DEFAULT '',
							`auto_topup` int(3) NOT NULL DEFAULT 0,
							`plant` varchar(50) NOT NULL DEFAULT '',
							`sv` varchar(20) NOT NULL DEFAULT '',
							`topup_time` varchar(20) NOT NULL DEFAULT '',
							`def_cgroup_id` int(11) NOT NULL DEFAULT 0,
							`term_id` int(11) NOT NULL DEFAULT 0,
							`wage_type` varchar(50) NOT NULL DEFAULT '',
							`desc` text DEFAULT '', 
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`), 
							KEY `code` (`code`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
					
			"customer_origin"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'cgroup_id',
							`name` varchar(250) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '',
							`parent` int(11) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							PRIMARY KEY (`id`),
							KEY `code` (`code`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
					
			"customer_origin_tree"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`ancestor` bigint(20) NOT NULL DEFAULT 0,
							`descendant` bigint(20) NOT NULL DEFAULT 0,
							`level` int(11) NOT NULL DEFAULT 1,
							PRIMARY KEY (`ancestor`,`descendant`),
							KEY `ancestor` (`ancestor`,`descendant`,`level`),
							KEY `descendant` (`descendant`,`level`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

			"member"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`customer_id` bigint(20) NOT NULL DEFAULT 0,
							`serial` varchar(50) NOT NULL DEFAULT '', 
							`pin` varchar(200) NOT NULL DEFAULT '',
							`total_debit` decimal(11,2) NOT NULL DEFAULT 0.00,
							`total_used` decimal(11,2) NOT NULL DEFAULT 0.00,
							`balance` decimal(11,2) NOT NULL DEFAULT 0.00,
							`point` decimal(11,2) NOT NULL DEFAULT 0.00,
							`phone_no` varchar(20) DEFAULT NULL,
							`email` varchar(100) NOT NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `customer_id` (`customer_id`,`status`), 
							KEY `serial` (`serial`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"membermeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`member_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `member_id` (`member_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"member_transact"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '',
							`member_id` varchar(20) NOT NULL DEFAULT '',
							`docno` varchar(30) NOT NULL DEFAULT '',
							`sdocno` varchar(30) NOT NULL DEFAULT '',
							`doc_type` varchar(50) NOT NULL DEFAULT 'topup',
							`amount` decimal(11,2) NOT NULL DEFAULT 0.00,
							`remarks` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`),
							KEY `member_id` (`member_id`,`warehouse_id`,`status`),
							KEY `doc_type` (`doc_type`,`warehouse_id`,`member_id`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"member_transactmeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`member_transact_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `member_transact_id` (`member_transact_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"customer_bankin_info"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`customer_id` bigint(20) NOT NULL DEFAULT 0,
							`receiver` varchar(250) NOT NULL DEFAULT '',
							`receiver_contact` varchar(20) NOT NULL DEFAULT '',
							`account_holder` varchar(250) NOT NULL DEFAULT '',
							`account_no` varchar(250) NOT NULL DEFAULT '',
							`bank` varchar(250) NOT NULL DEFAULT '',
							`bank_code` varchar(20) NOT NULL DEFAULT '',
							`bank_country` varchar(20) NOT NULL DEFAULT '',
							`bank_address` text DEFAULT '',
							`currency` varchar(20) NOT NULL DEFAULT '',
							`desc` text DEFAULT '',
							`reserved1` varchar(255) NOT NULL DEFAULT '',
							`reserved2` varchar(255) NOT NULL DEFAULT '',
							`reserved3` varchar(255) NOT NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `customer_id` (`customer_id`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"exchange_rate"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`docno` varchar(30) NOT NULL DEFAULT '', 
							`sdocno` varchar(30) NOT NULL DEFAULT '', 
							`title` varchar(250) NOT NULL DEFAULT '', 
							`from_currency` varchar(20) NOT NULL DEFAULT '',
							`to_currency` varchar(20) NOT NULL DEFAULT '',
							`base` decimal(20,3) NOT NULL DEFAULT '1.000',
							`rate` decimal(20,3) NOT NULL DEFAULT '0.000',
							`desc` text DEFAULT '',
							`since` varchar(50) NOT NULL DEFAULT '', 
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `from_currency` (`from_currency`,`to_currency`,`status`), 
							KEY `since` (`since`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"service_charge"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`code` varchar(50) NOT NULL DEFAULT '',
							`scode` varchar(50) NOT NULL DEFAULT '',
							`type` varchar(50) NOT NULL DEFAULT 'bank_in',
							`from_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`to_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`from_currency` varchar(20) NOT NULL DEFAULT 'MYR',
							`to_currency` varchar(20) NOT NULL DEFAULT 'DEF',
							`charge` decimal(20,2) NOT NULL DEFAULT '0.00',
							`charge_type` varchar(50) NOT NULL DEFAULT 'flat',
							`since` varchar(50) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `type` (`type`,`status`),
							KEY `charge` (`type`,`from_amt`,`to_amt`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"payment_method"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` ( 
							`id` int(11) NOT NULL AUTO_INCREMENT,
							`name` varchar(200) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '',
							`type` int(3) NOT NULL DEFAULT 0,
							`desc` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							PRIMARY KEY (`id`), 
							KEY `code` (`code`,`status`),
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"vending_machine"
    				=> "CREATE TABLE IF NOT EXISTS  `{tbname}` ( 
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'vending_machine_id', 
							`comp_id` bigint(20) NOT NULL DEFAULT 0,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '',
							`code` varchar(20) NOT NULL DEFAULT '', 
							`name` varchar(250) NOT NULL DEFAULT '', 
							`machine_no` varchar(50) NOT NULL DEFAULT '', 
							`desc` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1, 
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`), 
							KEY `code` (`code`,`status`), 
							KEY `status` (`status`,`flag`) 
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"vending_machinemeta"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							`vending_machine_id` bigint(20) unsigned NOT NULL DEFAULT 0,
							`meta_key` varchar(255) DEFAULT NULL,
							`meta_value` longtext,
							PRIMARY KEY (`meta_id`),
							KEY `vending_machine_id` (`vending_machine_id`,`meta_key`(151)),
							KEY `meta_key` (`meta_key`(151))
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"syncing"	
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'sync_id',
							`direction` varchar(30) NOT NULL DEFAULT 'out',
							`remote_url` varchar(100) NOT NULL DEFAULT '', 
							`wh_code` varchar(20) NOT NULL DEFAULT '',
							`section` varchar(100) NOT NULL DEFAULT '',
							`ref` varchar(100) NOT NULL DEFAULT '',
							`ref_id` bigint(20) NOT NULL DEFAULT 0,
							`details` longtext DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`handshake` bigint(20) NOT NULL DEFAULT 0,
							`notification` text DEFAULT '',
							`error` text DEFAULT '',
							`created_at` datetime NOT NULL DEFAULT current_timestamp, 
							`lupdate_at` datetime NOT NULL DEFAULT current_timestamp, 
							`lsync_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `direction` (`direction`,`wh_code`),
							KEY `sync` (`direction`,`remote_url`,`handshake`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"activity_log"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`wh_code` varchar(20) NOT NULL DEFAULT '', 
							`page` varchar(200) DEFAULT '',
							`section` varchar(200) DEFAULT '',
							`ref_id` bigint(20) NOT NULL DEFAULT 0,
							`action` varchar(100) NOT NULL DEFAULT '',
							`ip_address` varchar(50) NOT NULL DEFAULT '',
							`agent` varchar(200) NOT NULL DEFAULT '',
							`data` longtext DEFAULT '',
							`parent` bigint(20) NOT NULL DEFAULT 0,
							`status` int(3) NOT NULL DEFAULT 1,
							`error_remark` text DEFAULT '',
							`action_by` int(11) DEFAULT NULL, 
							`log_at` datetime NOT NULL DEFAULT current_timestamp, 
							PRIMARY KEY (`id`),
							KEY `page` (`page`,`section`),
							KEY `parent` (`parent`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"mailing_log"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`mail_id` varchar(200) NOT NULL DEFAULT '',
							`section` varchar(100) NOT NULL DEFAULT '', 
							`ref_id` varchar(20) DEFAULT '',
							`args` longtext DEFAULT '',
							`ip_address` varchar(50) NOT NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`error_remark` text DEFAULT '',
							`action_by` int(11) DEFAULT NULL, 
							`log_at` datetime NOT NULL DEFAULT current_timestamp, 
							PRIMARY KEY (`id`),
							KEY `mail_id` (`mail_id`,`status`),
							KEY `section` (`section`,`mail_id`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"selling_price"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`sales_item_id` bigint(20) NOT NULL,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '',
							`strg_id` bigint(20) NOT NULL DEFAULT 0,
							`customer` bigint(20) NOT NULL DEFAULT 0,
							`sales_date` datetime NOT NULL,
							`prdt_id` bigint(20) NOT NULL DEFAULT 0,
							`uom` varchar(20) NOT NULL DEFAULT '',
							`qty` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`unit` decimal(20,3) NOT NULL DEFAULT '0.000', 
							`uprice` decimal(20,5) NOT NULL DEFAULT '0.00000', 
							`price` decimal(20,5) NOT NULL DEFAULT '0.00000', 
							`total_amount` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`status` int NOT NULL DEFAULT 1, 
							PRIMARY KEY (`id`),
							KEY `warehouse_id` (`prdt_id` , `warehouse_id`),
							KEY `customer` (`prdt_id` , `customer`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"sales"			//sale_type: sales, refund
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`order_id` bigint(20) NOT NULL DEFAULT 0,
							`customer` bigint(20) NOT NULL DEFAULT 0,
							`sale_type` varchar(50) NOT NULL DEFAULT '',
							`plus_sign` varchar(1) DEFAULT NULL DEFAULT '',
							`credit_amt` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`paid_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`note` varchar(255) DEFAULT NULL,
							`parent` bigint(20) NOT NULL DEFAULT '0',
							`subtotal` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`subtotal_tax` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`discount` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`discount_tax` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`total` decimal(20,2) NOT NULL DEFAULT '0.00', 
							`sales_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', 
							PRIMARY KEY (`id`),
							KEY `customer` (`customer`,`credit_amt`),
							KEY `order_id` (`order_id`,`sale_type`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"stock_movement"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`warehouse_id` varchar(20) NOT NULL DEFAULT '',
							`strg_id` bigint(20) NOT NULL DEFAULT 0,
							`month` varchar(50) NOT NULL DEFAULT '',
							`product_id` bigint(20) NOT NULL DEFAULT 0, 
							`op_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`op_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`op_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`gr_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`gr_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`gr_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`rp_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`rp_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`rp_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`ti_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`ti_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`ti_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`dr_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`dr_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`dr_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`so_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`so_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`so_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`so_sale` decimal(20,2) NOT NULL DEFAULT '0.00',
							`to_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`to_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`to_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`gi_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`gi_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`gi_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`gt_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`gt_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`gt_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`adj_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`adj_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`adj_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`pos_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`pos_uom_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`pos_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`pos_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							`pos_sale` decimal(20,2) NOT NULL DEFAULT '0.00',
							`closing_qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`closing_mtr` decimal(20,3) NOT NULL DEFAULT '0.00',
							`closing_amt` decimal(20,2) NOT NULL DEFAULT '0.00',
							PRIMARY KEY (`id`),
							KEY `month` (`warehouse_id`,`strg_id`,`month`),
							KEY `product_id` (`warehouse_id`,`strg_id`,`month`,`product_id`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"margining"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`wh_id` varchar(20) NOT NULL DEFAULT '',
							`since` varchar(50) NOT NULL DEFAULT '', 
							`until` varchar(50) NOT NULL DEFAULT '', 
							`effective` VARCHAR(50) NOT NULL DEFAULT '',
							`inclusive` varchar(50) NOT NULL DEFAULT 'incl',
							`margin` decimal(20,2) NOT NULL DEFAULT 0.00,
							`round_type` varchar(50) NOT NUll DEFAULT '',
							`round_nearest` decimal(11,2) NOT NULL DEFAULT 0.00,
							`po_inclusive` VARCHAR(50) NOT NULL DEFAULT 'def',
							`type` VARCHAR(50) NOT NULL DEFAULT 'def',
							`remarks` text DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							`flag` int(3) NOT NULL DEFAULT 0,
							`created_by` int(11) NOT NULL DEFAULT 0, 
							`created_at` datetime DEFAULT NULL, 
							`lupdate_by` int(11) NOT NULL DEFAULT 0, 
							`lupdate_at` datetime DEFAULT NULL, 
							PRIMARY KEY (`id`),
							KEY `wh_id` (`wh_id`,`since`,`until`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"margining_sect"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`mg_id` bigint(20) NOT NULL DEFAULT 0,
							`section` varchar(200) NOT NULL DEFAULT '',
							`sub_section` varchar(200) NOT NULL DEFAULT '',
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `mg_id` (`mg_id`,`section`,`sub_section`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"margining_det"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`mg_id` bigint(20) NOT NULL DEFAULT 0,
							`client` varchar(50) NOT NULL DEFAULT '',
							`margin` decimal(20,2) NOT NULL DEFAULT 0.00,
							`status` int(3) NOT NULL DEFAULT 1,
							PRIMARY KEY (`id`),
							KEY `mg_id` (`mg_id`,`client`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",

			"margining_sales"
					=> "CREATE TABLE IF NOT EXISTS `{tbname}` (
							`id` bigint(20) NOT NULL AUTO_INCREMENT,
							`margining` bigint(20) NOT NULL DEFAULT 0,
							`type` varchar(50) NOT NULL DEFAULT 'def',
							`warehouse_id` varchar(20) NOT NULL DEFAULT '',
							`doc_id` bigint(20) NOT NULL DEFAULT 0,
							`item_id` bigint(20) NOT NULL DEFAULT 0,
							`product_id` bigint(20) NOT NULL DEFAULT 0, 
							`margin` decimal(20,2) NOT NULL DEFAULT '0.00',
							`doc_date` datetime NOT NULL, 
							`status` int(3) NOT NULL DEFAULT 0,
							`qty` decimal(20,2) NOT NULL DEFAULT '0.00',
							`foc` decimal(20,3) NOT NULL DEFAULT '0.00',
							`def_price` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`line_subtotal` decimal(20,2) NOT NULL DEFAULT '0.00',
							`line_discount` decimal(20,3) NOT NULL DEFAULT '0.00',
							`sprice` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`line_total` decimal(20,2) NOT NULL DEFAULT '0.00',
							`final_sprice` decimal(20,5) NOT NULL DEFAULT '0.00000',
							`line_final_total` decimal(20,2) NOT NULL DEFAULT '0.00',
							`order_subtotal` decimal(20,2) NOT NULL DEFAULT '0.00',
							`order_discount` decimal(20,2) NOT NULL DEFAULT '0.00',
							`order_total` decimal(20,2) NOT NULL DEFAULT '0.00',
							PRIMARY KEY (`id`),
							KEY `margining` (`margining`,`type`,`warehouse_id`),
							KEY `doc_id` (`warehouse_id`,`doc_id`,`doc_date`,`product_id`,`status`)
						) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;",
						
    	);
    }
	
	/**
	 *	Plugin activation
	 */
	public function activation()
	{
		$this->set_DB_sql();
		$this->install_DB();
		
		$this->create_roles( $this->plugin_ref['roles'] );
		//$this->add_admin_capabilities( $this->plugin_ref['capabilities'] );
		
		//initial data insertion
		$this->set_initial_data();

		//cron jobs
		$this->create_cron_jobs();
	}
	
	/**
	 *	Plugin deactivation
	 */
	public function deactivation()
	{
		$this->remove_roles( $this->plugin_ref['roles'] );
		//$this->remove_admin_capabilities( $this->plugin_ref['capabilities'] );
		
		$this->set_DB_sql();
		//$this->drop_DB();

		//cron jobs
		$this->deactivate_cron_jobs();
	}

	private function install_DB()
	{
		global $wpdb, $wcwh;

		$querys = $this->db_tables_sql;
		if( $querys ){
			foreach( $querys as $key => $sql ){
				$tbl = $wcwh->prefix.$key;
				$sql = str_replace( "{tbname}", $tbl, $sql );
				dbDelta( $sql );
			}
		}
	}
	
	private function drop_DB()
	{
		global $wpdb, $wcwh;

		$querys = $this->db_tables_sql;
		if( $querys ){
			$tbls = array();
			foreach( $querys as $key => $sql ){
				$tbls[] = "`".$wcwh->prefix.$key."`";
				
			}
			
			$table = implode( ", ", $tbls );
			$sql = "Drop TABLE {$table} ;";
			$wpdb->query( $sql );
		}
	}
	
	/**
	 *	Create roles and capabilities
	 */
	public function create_roles( $roles = array() )
	{
		if ( ! class_exists( 'WP_Roles' ) ) return;

		global $wp_roles;
		if( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();

		$r = [];
		foreach( $roles as $role => $infos )
		{
			if( ! $infos['capable'] ) continue;

			$r[] = $role;

			$copy = $wp_roles->get_role( $infos['copy_from'] );
			add_role( $role, $infos['name'], $copy->capabilities );
			
			/*foreach( $infos['capable'] as $cap )
			{
				$wp_roles->add_cap( $role, $cap );
				
			}*/
		}

		if( $r )
		{
			update_option( 'wcwh_roles', maybe_serialize( $r ) );
		}
	}

	/**
	 *	Addon Admin capabilities
	 */
	public function add_admin_capabilities( $caps = array() )
	{
		if( ! $caps ) return;

		global $wp_roles;
		foreach( $caps as $cap )
		{
			$wp_roles->add_cap( 'administrator', $cap );
		}
	}
	
	/**
	 *	Remove roles and capabilities
	 */
	public function remove_roles( $roles = array() ) 
	{
		if ( ! class_exists( 'WP_Roles' ) ) return;

		global $wp_roles;
		if( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();
		
		foreach( $roles as $role => $infos )
		{
			if( ! $infos['capable'] ) continue;
			
			/*foreach( $infos['capable'] as $cap )
			{
				$wp_roles->remove_cap( $role, $cap );
			}*/
			remove_role( $role );
		}
	}

	/**
	 *	Remove Admin capabilities
	 */
	public function remove_admin_capabilities( $caps = array() )
	{
		if( ! $caps ) return;

		global $wp_roles;
		foreach( $caps as $cap )
		{
			$wp_roles->remove_cap( 'administrator', $cap );
		}
	}
	
	/**
	 *	On Activation set default data
	 */
	public function set_initial_data()
	{
		wpdb_start_transaction();

		$this->set_initial_setting();

		$succ = $this->set_docno();
		$succ = $this->set_stockout();
		$succ = $this->set_scheme();
		$succ = $this->set_permission();
		$succ = $this->set_section();
		$succ = $this->set_statuses();

		$succ = $this->set_arrangement();
		$succ = $this->set_todo_action();

		wpdb_end_transaction( $succ );
	}
		protected function set_initial_setting()
		{
			update_option( 'wcwh_option', [
				'wh_sync' => [
					'data_per_connect' => 20,
					'connection_timeout' => 60,
				],
				'pos' => [
					'price_log' => '1',
					'pos_transaction' => '1',
				],
			] );
		}

		protected function set_docno()
		{
			global $wpdb, $wcwh;
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['doc_runningno'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			$docs = include( WCWH_DIR."/i18n/doc_types.php" );
			
			if( $exist <= 0 && $docs )
			{
				foreach( $docs as $type => $row )
				{
					$result = $wpdb->insert( $tbl,
						array( 
							'doc_type'	=> $type, 
							'type'		=> ( $row['type'] )? $row['type'] : 'default',
							'length'	=> ( $row['length'] )? $row['length'] : 7, 
							'prefix'	=> $row['prefix'],
							'suffix'	=> ( $row['suffix'] )? $row['suffix'] : '',
							'next_no'	=> isset( $row['next_no'] )? $row['next_no'] : 1,
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}
		protected function set_stockout()
		{
			global $wpdb, $wcwh;
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['stockout_method'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			$docs = apply_filters( 'wcwh_get_i18n', 'stockout' );
			
			if( $exist <= 0 && $docs )
			{
				foreach( $docs as $type => $row )
				{
					$result = $wpdb->insert( $tbl,
						array( 
							'ref_id'		=> $row['ref_id'], 
							'name'			=> $row['name'], 
							'order_type'	=> $row['order_type'],
							'ordering'		=> $row['ordering'],
							'priority'		=> $row['priority'],
							'default_value'	=> $row['default_value']
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}
		protected function set_scheme()
		{
			global $wpdb, $wcwh;
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['scheme'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			$schemes = apply_filters( 'wcwh_get_i18n', 'scheme' );
			
			if( $exist <= 0 && $schemes )
			{
				foreach( $schemes as $section )
				{
					foreach( $section['scheme'] as $scheme => $lvl )
					{
						$result = $wpdb->insert( $tbl,
							array( 
								'section'		=> $section['section'], 
								'section_id'	=> $section['section_id'],
								'scheme'		=> $scheme, 
								'scheme_lvl'	=> $lvl
							)
						);
						if( !$result )
							$succ = false;
					}
				}
			}
			
			return $succ;
		}
		protected function set_permission()
		{
			global $wpdb, $wcwh;
			$role_permissions = apply_filters( 'wcwh_get_i18n', 'role-permission' );
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['permission'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			if( $exist <= 0 && $role_permissions )
			{
				foreach( $this->plugin_ref['roles'] as $role => $infos )
				{	
					$caps = array();
					
					if( $role == 'administrator' )
					{
						foreach( $this->plugin_ref['capabilities'] as $cap )
						{
							$caps[] = $cap;
						}
					}
					else
					{
						foreach( $infos['capable'] as $cap )
						{
							$caps[] = $cap;
						}
					}
					
					if( $role_permissions[ $role ] )
					{
						foreach( $role_permissions[ $role ] as $section => $permission )
						{
							foreach( $permission as $rule )
								$caps[] = $rule."_".$section;
						}
					}
					
					$result = $wpdb->insert( $tbl,
						array( 
							'ref_id' => $role,
							'permission' => json_encode( $caps ),
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}
		protected function set_section()
		{
			global $wpdb, $wcwh;

			$sections = apply_filters( 'wcwh_get_i18n', 'section' );
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['section'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			if( $exist <= 0 && $sections )
			{
				foreach( $sections as $section => $args )
				{
					$result = $wpdb->insert( $tbl,
						array( 
							'section_id' => $section,
							'table' => $args['table'],
							'table_key' => $args['table_key'],
							'desc' => $args['desc'],
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}
		public function set_statuses()
		{
			global $wpdb, $wcwh;

			$statuses = apply_filters( 'wcwh_get_i18n', 'statuses' );
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['status'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			if( $exist <= 0 && $statuses )
			{
				foreach( $statuses as $args )
				{	
					$result = $wpdb->insert( $tbl,
						array( 
							'status' => $args['status'],
							'type' => $args['type'],
							'key' => $args['key'],
							'title' => $args['title'],
							'order' => $args['order'],
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}

		protected function set_arrangement()
		{
			global $wpdb, $wcwh;

			$arrangements = array( 
				array(
					'section' => 'wh_pricing',
					'match_status' => '1',
					'match_proceed' => '0',
					'match_halt' => '0',
					'action_type' => 'approval',
					'title' => '{docno}',
					'desc' => '',
					'order' => '1',
				),
			);
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['todo_arrangement'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			if( $exist <= 0 && $arrangements )
			{
				foreach( $arrangements as $arr )
				{
					$result = $wpdb->insert( $tbl,
						array( 
							'section' => $arr['section'],
							'match_status' => $arr['match_status'],
							'match_proceed' => $arr['match_proceed'],
							'match_halt' => $arr['match_halt'],
							'action_type' => $arr['action_type'],
							'title' => $arr['title'],
							'desc' => $arr['desc'],
							'order' => $arr['order'],
							'status' => '1',
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}
		protected function set_todo_action()
		{
			global $wpdb, $wcwh;

			$actions = array( 
				array(
					'arr_id' => 1,
					'next_action' => 'approve',
					'responsible' => 'approve_wh_pricing',
					'trigger_action' => 'approve',
				),
				array(
					'arr_id' => 1,
					'next_action' => 'reject',
					'responsible' => 'reject_wh_pricing',
					'trigger_action' => 'reject',
				),
			);
			
			$succ = true;
			$tbl = $wcwh->prefix.$this->db_tables['todo_action'];
			$exist = $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} " );
			
			if( $exist <= 0 && $actions )
			{
				foreach( $actions as $arr )
				{
					$result = $wpdb->insert( $tbl,
						array( 
							'arr_id' => $arr['arr_id'],
							'next_action' => $arr['next_action'],
							'responsible' => $arr['responsible'],
							'trigger_action' => $arr['trigger_action'],
						)
					);
					if( !$result )
						$succ = false;
				}
			}
			
			return $succ;
		}

	public function create_cron_jobs()
	{
		wp_clear_scheduled_hook( 'wcwh_scheduled_actions' );

		$ve = get_option( 'gmt_offset' ) < 0 ? '+' : '-';
		wp_schedule_event( strtotime( '06:00 ' . $ve . get_option( 'gmt_offset' ) . ' HOURS' ), 'twicehourly', 'wcwh_scheduled_actions' );

		//credit limit topup
		wp_clear_scheduled_hook( 'wcwh_scheduled_credit_topup' );
		wp_schedule_event( strtotime( '04:00 ' . $ve . get_option( 'gmt_offset' ) . ' HOURS' ), 'hourly', 'wcwh_scheduled_credit_topup' );
		
		//task schedule
		//wp_clear_scheduled_hook( 'wcwh_task_actions' );
		//wp_schedule_event( strtotime( '04:00 ' . $ve . get_option( 'gmt_offset' ) . ' HOURS' ), 'hourly', 'wcwh_task_actions' );
		
		//reminder
		wp_clear_scheduled_hook( 'wcwh_scheduled_daily_reminder' );
		wp_schedule_event( strtotime( '06:00 ' . $ve . get_option( 'gmt_offset' ) . ' HOURS' ), 'daily', 'wcwh_scheduled_daily_reminder' );
	}

	public function deactivate_cron_jobs()
	{
		wp_clear_scheduled_hook( 'wcwh_scheduled_actions' );
		wp_clear_scheduled_hook( 'wcwh_scheduled_credit_topup' );
		//wp_clear_scheduled_hook( 'wcwh_task_actions' );
		wp_clear_scheduled_hook( 'wcwh_scheduled_daily_reminder' );
	}
}

}