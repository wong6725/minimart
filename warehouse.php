<?php
/*
Plugin Name: Woocommerce Warehouse
Version: 2.9.2.4
Plugin URI: 
Author: STHB ICT Dev
Author URI: 
Description: Warehouse & Inventory system works together with Woocommerce & Point of Sale

*/

if ( !defined( "ABSPATH" ) )
    exit;
	
if ( !class_exists( "WCWH" ) )
{

class WCWH
{
	protected static $_instance = null;
	
	protected $plugin_ref = [];

	public $appid = "";

	public $prefix = "";

	public $debug = true;

	public $caps = [];

	protected $setting = [];
	
	/**
	 *	Instance for one time only
	 */
	public static function instance()
	{
        if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
        }
        return self::$_instance;
    }
	
	public function __construct()
	{
		$this->set_setting();
		$this->set_variables();
		$this->update_initial();
		$this->define_constants();
        $this->init_hooks();
    }

    public function __destruct()
    {
    	unset($this->setting);
    	unset($this->plugin_ref);
    }

    protected function set_setting()
    {
    	$this->setting = get_option( 'wcwh_option', [] );
    }

    public function get_setting()
    {
    	return $this->setting;
    }

    protected function set_variables()
    {
    	$this->plugin_ref = [
    		"version" => "2.9.2",
			"id" => "wcwh",
			"einv_id" => "mnmart",
			"einv_start" => "2025-01-01",
			//"einv_url" => "http://172.16.110.85/einvoice_demo/",
			"einv_url" => "http://172.16.110.85/myinvoice/",
			//"irb_url" => "https://preprod.myinvois.hasil.gov.my/",
			"irb_url" => "https://myinvois.hasil.gov.my/",
			"starting" => "2020-08-01",
			"cap_section" => [
				"general" => "General Permission",
				"pos" => "POS Permission",
			],
			"capabilities" => [
				"wcwh_user", 
				"wcwh_operator", 
				"dc_user", 
				"store_user",
				//
				"wh_super_admin",
				"wh_configure",
				//Global capabilities
				"wh_dc_supervisor",
				"wh_dc_executive",
				"wh_dc_officer",
				"wh_store_supervisor",
				"wh_store_executive",
				"wh_store_officer",
				"wh_auditable",
				"wh_support",
				"wh_admin_support",
				//others
				"wh_view_permission",
				"wh_manage_permission",
				"wh_manage_role",
				"wh_maintain_user",
				"wh_can_debug",
			],
			"roles" => [
				"administrator" => [
					"name" => "Administrator",
				],
				"system_supervisor" => [ 
					"name" => "System Supervisor", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", "dc_user", "wh_super_admin", 
						"wh_dc_supervisor", "wh_store_supervisor", "wh_auditable", 
						"wh_manage_permission", "wh_maintain_user", "wh_configure", "wh_support", "wh_admin_support",
						//POS Manager
						"view_register", "read_private_products", "read_private_shop_coupons", "read_private_shop_orders", "edit_shop_order", 
						"manage_wc_point_of_sale", "view_woocommerce_reports"
					], 
					"copy_from" => "editor" 
				], 
				"warehouse_supervisor" => [ 
					"name" => "DC Supervisor", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", "dc_user", 
						"wh_dc_supervisor", "wh_auditable", 
					], 
					"copy_from" => "editor" 
				], 
				"warehouse_executive" => [ 
					"name" => "DC Executive", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", "dc_user", 
						"wh_dc_executive", 
					], 
					"copy_from" => "author" 
				], 
				"warehouse_officer" => [ 
					"name" => "DC Officer", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", "dc_user", 
						"wh_dc_officer", 
					], 
					"copy_from" => "contributor" 
				], 
				"store_supervisor" => [ 
					"name" => "Store Supervisor", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", "store_user", 
						"wh_store_supervisor", "wh_auditable",
						//POS Manager
						"view_register", "read_private_products", "read_private_shop_coupons", "read_private_shop_orders", "edit_shop_order", 
						"manage_wc_point_of_sale", "view_woocommerce_reports"
					], 
					"copy_from" => "editor" 
				], 
				"store_executive" => [ 
					"name" => "Store Executive", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator",  "store_user", 
						"wh_store_executive", 
						//POS Manager
						"view_register", "read_private_products", "read_private_shop_coupons", "read_private_shop_orders", "edit_shop_order", 
						"manage_wc_point_of_sale", "view_woocommerce_reports"
					], 
					"copy_from" => "author" 
				], 
				"store_officer" => [ 
					"name" => "Store Officer", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator",  "store_user", 
						"wh_store_officer", 
						//POS Cashier
						"view_register", "read_private_products", "read_private_shop_coupons", "read_private_shop_orders", "edit_shop_order", 
					], 
					"copy_from" => "contributor" 
				], 
				"hr_officer" => [ 
					"name" => "HR Officer", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", 
					], 
					"copy_from" => "editor" 
				], 
				"auditor" => [ 
					"name" => "Auditor", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", 
						"wh_auditable",
					], 
					"copy_from" => "editor" 
				], 
				"accountant" => [ 
					"name" => "Accountant", 
					"capable" => [ 
						"wcwh_user", "wcwh_operator", 
					], 
					"copy_from" => "editor" 
				], 
				"maintainer" => [ 
					"name" => "Maintainer", 
					"capable" => [ 
						"wcwh_user", 
						"wh_manage_permission", "wh_maintain_user", "wh_configure", "wh_support",
					], 
					"copy_from" => "author" 
				], 
				"supporter" => [ 
					"name" => "Supporter", 
					"capable" => [ 
						"wcwh_user", 
						"wh_support", "wh_support", "wh_support", "wh_admin_support",
					], 
					"copy_from" => "author" 
				], 
			],
			"menu" => [
//----------------------------------------------------------------------

//"wh_dashboard" 		=> [
//	"wh_dashboard" 		=> [ "title" => "Dashboard", "capability" => "wcwh_user", "order" => "57.0", "icon" => 'dashicons-dashboard' ],
//],

"wh_debug" 		=> [
	"wh_debug" 		=> [ "title"=>"Developer Debug", "capability"=>"manage_options", "order"=>"57.01", "icon"=>'' ],
	"wh_check" 		=> [ "title"=>"Developer Check", "capability"=>"manage_options" ],
],
"wh_todo" 		=> [
	"wh_todo" 		=> [ "title"=>"Todo", "capability"=>"access_wh_todo", "order"=>"57.05", "icon"=>'dashicons-todo' ],
],
"wh_company" 	=> [ 
	"wh_company" 	=> [ "title"=>"Company", "capability"=>"access_wh_company", "order"=>"57.10", "icon"=>"dashicons-building" ],
],
"wh_warehouse" 	=> [
	"wh_warehouse" 	=> [ "title"=>"Warehouse / Store", "capability"=>"wh_super_admin", "order"=>"57.11", "icon"=>"dashicons-admin-multisite", 
		"description"=>"Warehouse or Store which has ability for inventory or Selling" ],
],
"wh_supplier"	=> [
	"wh_supplier" 	=> [ "title"=>"Supplier", "capability"=>"access_wh_supplier", "order"=>"57.12", "icon"=>"dashicons-factory" ],
],
"wh_asset" 		=> [
	"wh_asset" 			=> [ "title"=>"Assets", "capability"=>"access_wh_asset", "order"=>"57.13", "icon"=>"dashicons-good" ],
	"wh_asset_movement"	=> [ "title"=>"Asset Movements", "capability"=>"access_wh_asset", 'tab'=>'movement', 'id'=>'wh_asset' ],
],
"wh_vending_machine"	=> [
	"wh_vending_machine"	=> [ "title"=>"Vending Machine", "capability"=>"access_wh_vending_machine", "order"=>"57.14", "icon"=>"dashicons-editor-insertmore" ],
],
"wh_brand"		=> [
	"wh_brand" 		=> [ "title"=>"Brand", "capability"=>"access_wh_brand", "order"=>"57.15", "icon"=>"dashicons-tag" ],
	"wh_criteria" 	=> [ "title"=>"Criteria", "capability"=>"access_wh_brand", "order"=>"57.15", "icon"=>"dashicons-tag" ],
],
"wh_items" 		=> [
	"wh_items" 				=> [ "title"=>"Items", "capability"=>"access_wh_items", "order"=>"57.16", "icon"=>"dashicons-goods" ],
	"wh_items_relation"		=> [ "title"=>"Items to Order Type Relation", "capability"=>"access_wh_items_relation" ],
	"wh_items_group"		=> [ "title"=>"Item Group", "capability"=>"access_wh_items_group" ],
	"wh_items_store_type"	=> [ "title"=>"Item Storing Type", "capability"=>"access_wh_items_store_type" ],
	"wh_items_category"		=> [ "title"=>"Item Category", "capability"=>"access_wh_items_category" ],
	"wh_items_order_type"	=> [ "title"=>"Item Order Type", "capability"=>"access_wh_items_order_type" ],
	"wh_uom" 				=> [ "title"=>"Unit of Measure", "capability"=>"access_wh_uom" ],
	"wh_uom_conversion" 	=> [ "title"=>"UOM Conversion", "capability"=>"access_wh_uom_conversion" ],
	"wh_reprocess_item" 	=> [ "title"=>"Reprocess Item", "capability"=>"access_wh_reprocess_item" ],
	"wh_itemize" 			=> [ "title"=>"Itemize", "capability"=>"access_wh_itemize" ],
	"wh_item_scan"			=> [ "title"=>"Item Scan", "capability"=>"access_wh_item_scan" ],
	"wh_item_expiry"		=> [ "title"=>"Item Expiry", "capability"=>"access_wh_item_expiry" ],
],
"wh_pricing" 	=> [
	"wh_pricing"		=> [ "title"=>"Pricing", "capability"=>"access_wh_pricing", "order"=>"57.17", "icon"=>"dashicons-pricing" ],
	"wh_pricing_manage"	=> [ "title"=>"Manage Price", "capability"=>"access_wh_pricing", 'tab'=>'manage-price', 'id'=>'wh_pricing' ],
	"wh_pricing_margin"	=> [ "title"=>"Manage Margin", "capability"=>"access_wh_margin", 'tab'=>'manage-margin', 'id'=>'wh_pricing' ],
	"wh_purchase_pricing" 	=> [ "title"=>"Purchase Pricing", "capability"=>"access_wh_purchase_pricing" ],
	"wh_promo"			=> [ "title"=>"Promotion", "capability"=>"access_wh_promo", "order"=>"57.17", "icon"=>"dashicons-products" ],
],
"wh_client"		=> [
	"wh_client" 	=> [ "title"=>"Client", "capability"=>"access_wh_client", "order"=>"57.18", "icon"=>"dashicons-groups" ],
],
"wh_customer" 		=> [
	"wh_customer"		=> [ "title"=>"Customer", "capability"=>"access_wh_customer", "order"=>"57.20", "icon"=>"dashicons-groups" ],
	"wh_customer_job"	=> [ "title"=>"Customer Job", "capability"=>"access_wh_customer_job" ],
	"wh_customer_group"	=> [ "title"=>"Customer Group", "capability"=>"access_wh_customer_group" ],
	"wh_origin_group"	=> [ "title"=>"Customer Origin", "capability"=>"access_wh_origin_group" ],
	"wh_account_type"	=> [ "title"=>"Account Type", "capability"=>"access_wh_account_type" ],
],
"wh_credit" 		=> [
	"wh_credit"			=> [ "title"=>"Credit Limit", "capability"=>"access_wh_credit", "order"=>"57.21", "icon"=>'dashicons-credit' ],
	"wh_credit_term" 	=> [ "title"=>"Credit Term", "capability"=>"access_wh_credit_term", "order"=>"57.21", "icon"=>'dashicons-credit' ],
	"wh_credit_topup"	=> [ "title"=>"Credit TopUp", "capability"=>"access_wh_credit_topup", "order"=>"57.21", "icon"=>'dashicons-credit' ],
],
"wh_membership"	=> [
	"wh_membership" => [ "title"=>"Membership", "capability"=>"access_wh_membership", "order"=>"57.22", "icon"=>"dashicons-id" ],
	"wh_member_topup" => [ "title"=>"Member TopUp", "capability"=>"access_wh_member_topup", "order"=>"57.22", "icon"=>'dashicons-id' ],
],
"wh_money_collector" 		=> [
	"wh_money_collector"=> [ "title"=>"Money Collector", "capability"=>"access_wh_money_collector", "order"=>"57.23","icon"=>'dashicons-credit' ],
	"wh_pos_cash_withdrawal" 	=> [ "title"=>"Cash Withdrawal", "capability"=>"access_wh_pos_cash_withdrawal", "order"=>"57.23" ],
	"wh_uncollected_money_rpt"	=> [ "title"=>"Money Collector Report", "capability"=>"access_wh_uncollected_money_rpt", "order"=>"57.24"],
],

//Bank In Service
"wh_bankin_service" => [
	"wh_bankin_service"	=> [ "title"=>"Remittance Money Service", "capability"=>"access_wh_bankin_service", "order"=>"57.24", "icon"=>"dashicons-groups" ],
	"wh_bankin_collector" => [ "title"=>"Remittance Money Collector", "capability"=>"access_wh_bankin_collector" ],
	"wh_exchange_rate"	=> [ "title"=>"Exchange Rate", "capability"=>"access_wh_exchange_rate" ],
	"wh_service_charge"	=> [ "title"=>"Service Charge", "capability"=>"access_wh_service_charge" ],
	"wh_bankin_info"	=> [ "title"=>"Customer Remittance Info", "capability"=>"access_wh_bankin_info" ],
	"wh_bankin_service_rpt"	=> [ "title"=>"Remittance Report", "capability"=>"access_wh_bankin_service_rpt" ],
],
//Tools Requisition
"wh_tool_request" => [
	"wh_tool_request"	=> [ "title"=>"Tools Requisition", "capability"=>"access_wh_tool_request", "order"=>"57.25", "icon"=>"dashicons-admin-tools" ],
	"wh_tool_request_fulfilment" => [ "title"=>"Tool Request Fulfilment", "capability"=>"access_wh_tool_request_fulfilment", "order"=>"57.25", "icon"=>'dashicons-reports' ],
	"wh_tool_request_rpt"	=> [ "title"=>"Tool Request Reports", "capability"=>"access_wh_tool_request_rpt", "order"=>"57.25", "icon"=>'dashicons-reports' ],
],
//Parts Requisition
"wh_parts_request" => [
	"wh_parts_request"	=> [ "title"=>"Spare Parts Request", "capability"=>"access_wh_parts_request", "order"=>"57.26", "icon"=>"dashicons-admin-tools" ],
],
"wh_other_mst" 		=> [
	"wh_other_mst"		=> [ "title"=>"Other Master", "capability"=>"access_wh_other_mst", "order"=>"57.27", "icon"=>'dashicons-index-card' ],
	"wh_payment_method"	=> [ "title"=>"Payment Method", "capability"=>"access_wh_payment_method", "order"=>"57.27", "icon"=>'dashicons-index-card' ],
	"wh_payment_term"	=> [ "title"=>"Payment Term", "capability"=>"access_wh_payment_term", "order"=>"57.27", "icon"=>'dashicons-index-card' ],
],
"wh_margining"		=> [
	"wh_margining"		=> [ "title"=>"Margining Control", "capability"=>"access_wh_margining", "order"=>"57.29", "icon"=>'dashicons-sort' ],
],
//----------------------------------------------------------------------
"wh_reports"		=> [
	"wh_reports"			=> [ "title"=>"Reports", "capability"=>"view_wh_reports", "order"=>"57.30", "icon"=>'dashicons-reports' ],
],
"wh_customer_rpt"	=> [
	"wh_customer_rpt"		=> [ "title"=>"Customer Reports", "capability"=>"access_customer_purchases_wh_reports", "order"=>"57.31", "icon"=>'dashicons-reports' ],
	"wh_credit_rpt"		=> [ "title"=>"Credit Reports", "capability"=>"access_credit_wh_reports", "order"=>"57.31", "icon"=>'dashicons-reports' ],
	"wh_tool_rpt"		=> [ "title"=>"Tools Credit Reports", "capability"=>"access_tool_wh_reports", "order"=>"57.31", "icon"=>'dashicons-reports' ],
	//"wh_credit_limit_rpt"	=> [ "title"=>"Credit Limit Listing", "capability"=>"access_credit_wh_reports", "order"=>"57.31", "icon"=>'dashicons-reports' ],
	"wh_receipt_count"		=> [ "title"=>"Total Receipts", "capability"=>"access_receipt_wh_reports", "order"=>"57.31", "icon"=>'dashicons-reports' ],
],
"wh_pos_rpt"		=> [
	"wh_pos_rpt"		=> [ "title"=>"POS Reports", "capability"=>"access_pos_wh_reports", "order"=>"57.32", "icon"=>'dashicons-reports' ],
	"wh_pos_cost_rpt"	=> [ "title"=>"POS Cost & Sales", "capability"=>"access_pos_cost_wh_reports", "order"=>"57.32", "icon"=>'dashicons-reports' ],
],
"wh_purchase_rpt"=> [
	"wh_purchase_rpt"	=> [ "title"=>"Purchase Reports", "capability"=>"access_purchase_wh_reports", "order"=>"57.33", "icon"=>'dashicons-reports' ],
],
"wh_sales_rpt"=> [
	"wh_sales_rpt"	=> [ "title"=>"Sales Reports", "capability"=>"access_sales_wh_reports", "order"=>"57.34", "icon"=>'dashicons-reports' ],
	"wh_momawater_rpt"	=> [ "title"=>"MOMAwater Report", "capability"=>"access_momawater_wh_reports", "order"=>"57.35", "icon"=>'dashicons-reports' ],
],
"wh_inventory_rpt"=> [
	"wh_inventory_rpt"	=> [ "title"=>"Inventory Reports", "capability"=>"access_inventory_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_movement_rpt"	=> [ "title"=>"Stock Movement Report", "capability"=>"access_movement_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	// in/out
	"wh_inout_rpt"	=> [ "title"=>"In/Out Report", "capability"=>"access_inout_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_balance_rpt"	=> [ "title"=>"Stock Balance Report", "capability"=>"access_balance_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_itemize_rpt"		=> [ "title"=>"Itemize Report", "capability"=>"access_itemize_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_reorder_rpt"		=> [ "title"=>"Reorder Report", "capability"=>"access_reorder_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_unprocessed_doc_rpt"	=> [ "title"=>"UnProcessed Document", "capability"=>"access_unprocessed_doc_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_discrepancy_rpt"	=> [ "title"=>"Discrepancy Report", "capability"=>"access_discrepancy_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_stock_aging_rpt"	=> [ "title"=>"Stock Aging Report", "capability"=>"access_stock_aging_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
	"wh_transaction_log_rpt"	=> [ "title"=>"Transaction Log", "capability"=>"access_transaction_log_wh_reports", "order"=>"57.37", "icon"=>'dashicons-reports' ],
],
"wh_hr_rpt"=> [
	"wh_hr_rpt"			=> [ "title"=>"HR Monitoring Reports", "capability"=>"access_hr_wh_reports", "order"=>"57.38", "icon"=>'dashicons-reports' ],
	"wh_foodboard_rpt"	=> [ "title"=>"FoodBoard Report", "capability"=>"access_foodboard_wh_reports", "order"=>"57.38", "icon"=>'dashicons-reports' ],
	"wh_estate_rpt"		=> [ "title"=>"Estate Office Report", "capability"=>"access_estate_wh_reports", "order"=>"57.38", "icon"=>'dashicons-reports' ],
	"wh_estate_expenses_rpt" => [ "title"=>"Estate Expenses Report", "capability"=>"access_estate_expenses_wh_reports", "order"=>"57.38", "icon"=>'dashicons-reports' ],
	"wh_et_price_rpt"	=> [ "title"=>"Foodboard/Estate Pricing", "capability"=>"access_et_price_wh_reports", "order"=>"57.38", "icon"=>'dashicons-reports' ],
	"wh_trade_in_rpt"	=> [ "title"=>"Trade In Report", "capability"=>"access_trade_in_wh_reports", "order"=>"57.38", "icon"=>'dashicons-reports' ],
],
"wh_intercom_rpt"=> [
	"wh_intercom_rpt"	=> [ "title"=>"Inter-Company Billing", "capability"=>"access_wh_intercom_wh_reports", "order"=>"57.39", "icon"=>'dashicons-reports' ],
	"wh_intercom_company_rpt"	=> [ "title"=>"For Company", "page_title"=>"Inter-Company Billing For Company", "capability"=>"access_intercom_company_wh_reports", "order"=>"57.39", "icon"=>'dashicons-reports' ],
	"wh_intercom_worker_rpt"	=> [ "title"=>"For Worker", "page_title"=>"Inter-Company Billing For Worker", "capability"=>"access_intercom_worker_wh_reports", "order"=>"57.39", "icon"=>'dashicons-reports' ],
],
//----------------------------------------------------------------------
"wh_charts"		=> [
	"wh_charts"				=> [ "title"=>"Charts", "capability"=>"view_wh_charts", "order"=>"57.60", "icon"=>'dashicons-chart-line' ],
	"wh_pos_overall_chart"	=> [ "title"=>"POS Overall", "capability"=>"access_pos_overall_wh_charts", "order"=>"57.60", "icon"=>'dashicons-chart-line' ],
	"wh_pos_chart"			=> [ "title"=>"POS Sales", "capability"=>"access_pos_wh_charts", "order"=>"57.60", "icon"=>'dashicons-chart-line' ],
	"wh_foodboard_chart"	=> [ "title"=>"FoodBoard", "capability"=>"access_foodboard_wh_charts", "order"=>"57.60", "icon"=>'dashicons-chart-line' ],
	"wh_estate_chart"		=> [ "title"=>"Estate Office", "capability"=>"access_estate_wh_charts", "order"=>"57.60", "icon"=>'dashicons-chart-line' ],
],
"wh_acc_period"		=> [
	"wh_acc_period"		=> [ "title"=>"Accounting Period", "capability"=>"access_wh_acc_period", "order"=>"57.70", "icon"=>'dashicons-clock' ],
	"wh_stocktake_close" => [ "title"=>"Stock Take Control", "capability"=>"access_wh_stocktake_close", "order"=>"57.70", "icon"=>'dashicons-clock' ],
],
//--------22/11/2022 Repleaceable
"wh_repleaceable" 		=> [
	"wh_repleaceable" 		=> [ "title"=>"Replaceable", "capability"=>"access_wh_repleaceable", "order"=>"57.79", "icon"=>'dashicons-controls-repeat' ],
],
//--------22/11/2022 Repleaceable
"wh_profile" 		=> [
	"wh_profile"		=> [ "title"=>"User Profile", "capability"=>"access_wh_profile", "order"=>"58.10", "icon"=>'dashicons-user-profile' ],
	"wh_maintain_user" 	=> [ "title"=>"Maintain Users", "capability"=>"wh_maintain_user", "order"=>"58.10", "icon"=>'dashicons-user-profile' ],
	"wh_permission" 	=> [ "title"=>"Access Right", "capability"=>"wh_manage_permission", "order"=>"58.10", "icon"=>'dashicons-user-profile' ],
	"wh_roles" 			=> [ "title"=>"Manage Role", "capability"=>"wh_manage_role", "order"=>"58.10", "icon"=>'dashicons-user-profile' ],
],
"wh_support" 		=> [
	"wh_support"		=> [ "title"=>"Support", "capability"=>"wh_support", "order"=>"58.20", "icon"=>'dashicons-editor-help' ],
	"wh_stage"			=> [ "title"=>"Document Stage", "capability"=>"wh_support", "order"=>"58.20", "icon"=>'dashicons-editor-help' ],
	"wh_sync"			=> [ "title"=>"Sync Bridge", "capability"=>"wh_support", "order"=>"58.20", "icon"=>'dashicons-editor-help' ],
	"wh_logs"			=> [ "title"=>"Activity Logs", "capability"=>"wh_support", "order"=>"58.20", "icon"=>'dashicons-editor-help' ],
	"wh_mail_logs"		=> [ "title"=>"Mailing Logs", "capability"=>"wh_support", "order"=>"58.20", "icon"=>'dashicons-editor-help' ],
],
"wh_config" 		=> [
	"wh_config"			=> [ "title"=>"Configuration", "capability"=>"wh_configure", "order"=>"58.30", "icon"=>'dashicons-admin-settings' ],
	"wh_arrangement"	=> [ "title"=>"Arrangement", "capability"=>"wh_configure", "order"=>"58.30", "icon"=>'dashicons-admin-settings' ],
	"wh_template"		=> [ "title"=>"Templates", "capability"=>"wh_configure", "order"=>"58.30", "icon"=>'dashicons-admin-settings' ],
],
"wh_pos_session" 	=> [
	"wh_pos_session"	=> [ "title"=>"POS Session", "capability"=>"view_register", "order"=>"55.9", "icon"=>'dashicons-welcome-widgets-menus' ],
	"wh_pos_order"		=> [ "title"=>"POS Orders", "capability"=>"manage_pos_order", "order"=>"55.9", "icon"=>'dashicons-welcome-widgets-menus' ],
	"wh_pos_cdn"		=> [ "title"=>"POS C/D Note", "capability"=>"manage_pos_order", "order"=>"55.9", "icon"=>'dashicons-welcome-widgets-menus' ],
	"wh_pos_do" 		=> [ "title"=>"POS Delivery Order", "capability"=>"access_wh_pos_do", "order"=>"57.01", "icon"=>'dashicons-format-aside' ],
	"wh_pos_price"		=> [ "title"=>"POS Price Log", "capability"=>"wh_support", "order"=>"55.9", "icon"=>'dashicons-welcome-widgets-menus' ],
	//"wh_pos_cash_withdrawal"		=> [ "title"=>"Cash Withdrawal", "capability"=>"manage_pos_cash_withdrawal", "order"=>"55.9", "icon"=>'dashicons-welcome-widgets-menus' ],
	"wh_pos_credit"		=> [ "title"=>"POS Credit Log", "capability"=>"wh_support", "order"=>"55.9", "icon"=>'dashicons-welcome-widgets-menus' ],
],

"wh_search_tin" 			=> [
	"wh_search_tin" 	=> [ "title"=>"Search Tin", "capability"=>"access_wh_search_tin", "order"=>"58.50", "icon"=>"dashicons-search" ],
],

//task schedule
"wh_task_schedule"		=> [
	"wh_task_schedule"		=> [ "title"=>"Task Schedule", "capability"=>"access_wh_task_schedule", "order"=>"57.79", "icon"=>'dashicons-clock' ],
	"wh_task_checklist"		=> [ "title"=>"Task Checklist", "capability"=>"access_wh_task_checklist" ],
],

//----------------------------------------------------------------------
			],
"branch_menu" => [
	"wh_branch" 		=> [ 
		"wh_inventory" 			=> [ "title"=>"Inventory", "capability"=>"access_wh_inventory", "order"=>"57.8{i}", "icon"=>'dashicons-admin-multisite' ],
		"wh_purchase_request" 	=> [ "title"=>"Purchase Request", "capability"=>"access_wh_purchase_request", "cap"=>[ 'purchase_request' ] ],
		"wh_purchase_order" 	=> [ "title"=>"Purchase Order", "capability"=>"access_wh_purchase_order", "cap"=>[ 'purchase_order' ] ],
		"wh_self_bill" 			=> [ "title"=>"Self Bill E-Invoice", "capability"=>"access_wh_self_bill", "cap"=>[ 'purchase_order' ] ],
		"wh_good_receive" 		=> [ "title"=>"Goods Receipt", "capability"=>"access_wh_good_receive", "cap"=>[ 'good_receive' ] ],
		"wh_good_return" 		=> [ "title"=>"Goods Return", "capability"=>"access_wh_good_return", "cap"=>[ 'good_return' ] ],
		"wh_reprocess" 			=> [ "title"=>"Reprocess", "capability"=>"access_wh_reprocess", "cap"=>[ 'reprocess' ] ],
		////-----------------------------jeff----------------//////////
		"wh_simplify_reprocess"	=> [ "title"=>"Simplify Reprocess", "capability"=>"access_wh_simplify_reprocess", "cap"=>[ 'reprocess' ] ],
		////-----------------------------jeff----------------//////////
		"wh_sales_order" 		=> [ "title"=>"Sales Order", "capability"=>"access_wh_sales_order", "cap"=>[ 'sales_order' ] ],
		"wh_e_invoice" 			=> [ "title"=>"E-Invoice", "capability"=>"access_wh_e_invoice", "cap"=>[ 'sales_order' ] ],
		"wh_sales_return" 		=> [ "title"=>"Sales Return", "capability"=>"access_wh_sales_return", "cap"=>[ 'sales_order' ] ],
		"wh_transfer_order" 	=> [ "title"=>"Transfer Order", "capability"=>"access_wh_transfer_order", "cap"=>[ 'transfer_order' ] ],
		"wh_delivery_order" 	=> [ "title"=>"Delivery Order", "capability"=>"access_wh_delivery_order", "cap"=>[ 'delivery_order' ] ],
		"wh_good_issue"			=> [ "title"=>"Goods Issue", "capability"=>"access_wh_good_issue", "cap"=>[ 'good_issue' ] ],
		"wh_issue_return"		=> [ "title"=>"Issue Return", "capability"=>"access_wh_issue_return", "cap"=>[ 'good_issue' ] ],
		"wh_block_stock"		=> [ "title"=>"Block Stock", "capability"=>"access_wh_block_stock", "cap"=>[ 'block_stock' ] ],
		"wh_transfer_item" 		=> [ "title"=>"Transfer Item", "capability"=>"access_wh_transfer_item", "cap"=>[ 'transfer_item' ] ],
		"wh_stock_adjust" 		=> [ "title"=>"Stock Adjustment", "capability"=>"access_wh_stock_adjust", "cap"=>[ 'stock_adjust' ] ],
		"wh_stocktake" 			=> [ "title"=>"StockTake", "capability"=>"access_wh_stocktake", "cap"=>[ 'stocktake' ] ],
		"wh_stock_movement_rectify" => [ "title"=>"Stock Movement Correction", "capability"=>"access_wh_stock_movement_rectify", "cap"=>[ 'stock_adjust' ] ],
		"wh_pos_transact"		=> [ "title"=>"POS Transaction", "capability"=>"access_wh_pos_transact", "cap"=>[ 'pos_transact' ] ],
		"wh_storing_list" 		=> [ "off"=>true, "title"=>"Storing", "capability"=>"access_wh_storing_list", "cap"=>[ 'storing' ] ],
		"wh_picking_list" 		=> [ "off"=>true, "title"=>"Picking", "capability"=>"access_wh_picking_list", "cap"=>[ 'picking' ] ],
		//"wh_invoicing" 		=> [ "off"=>true, "title"=>"Invoicing", "capability"=>"access_wh_invoicing", "cap"=>[ 'invoicing' ] ],
		"wh_storage" 			=> [ "title"=>"Storage Location", "capability"=>"access_wh_storage", "cap"=>[ 'storage' ] ],
	],
],
			"access_menu" => [
				"upload.php",
				"edit-tags.php?taxonomy=link_category",
				"edit-comments.php",
				"edit.php",
				"edit.php?post_type=page",
				"themes.php",
				"plugins.php",
				"tools.php",
				"options-general.php",
			],
			"actions" => [
				"save"				=> "New",
				"update"			=> "Edit",
				//link type action
				"view"				=> "View",
				"edit"				=> "Edit",
				"duplicate"			=> "Duplicate",
				"delete"			=> "Delete",
				"confirm"			=> "Confirm",
				"refute"			=> "Refute",
				"on-hold"			=> "On Hold",
				"recall"			=> "Recall",
				"approve"			=> "Approve",
				"post"				=> "Posting",
				"unpost"			=> "UnPost",
				"complete"			=> "Complete",
				"incomplete"		=> "Revert Complete",
				"close"				=> "Close",
				"reopen"			=> "Re-Open",
				"reject"			=> "Reject",
				"regret"			=> "Regret",
				"restore"			=> "Restore",
				"remove"			=> "Remove",
				"cancel"			=> "Cancel",
				"print"				=> "Print",
				"email"				=> "Email",
				"perform"			=> "Perform",
				"delete-permanent"	=> "Permanently Delete",
				"qr"				=> "QR Code",
				"barcode"			=> "Barcode",
				"import"			=> "Import",
				"export"			=> "Export",
				"sync"				=> "Sync",
				"trash"				=> "Trash",
				//"view_doc"			=> "View",
			],
			"action_type" => [
				"approval"		=> "Approval",
				"processing"	=> "Processing",
				"confirmation"	=> "Confirmation",
			],
			"type_actions" => [
				"approval"		=> [ "approve", "reject" ],
				"processing"	=> [],
				"confirmation"	=> [ "confirm", "refute" ],
			],
			"status" => [
				-1	=> "deleted",
				0	=> "inactive",
				1	=> "active",
				3	=> "confirm",
				6	=> "posted",
				9	=> "completed",
				10	=> "closed",
			],
			"action_statuses" => [
				0 	=> [ "key" => "normal" ],
				10	=> [ "key" => "reopen" ],
				20	=> [ "key" => "confirmed", "action" => "confirm" ],
				50	=> [ "key" => "approved", "action" => "approve" ],
				60	=> [ "key" => "processing" ],
				80	=> [ "key" => "posted", "action" => "post" ],
				90	=> [ "key" => "completed", "action" => "complete" ],
				100	=> [ "key" => "closed", "action" => "close" ],
				-10 => [ "key" => "locked" ],
				-20 => [ "key" => "refute" ],
				-30 => [ "key" => "recall", "action" => "recall" ],
				-40 => [ "key" => "rejected", "action" => "reject" ],
				-55 => [ "key" => "regret", "action" => "regret" ],
				-70 => [ "key" => "on-hold", "action" => "on-hold" ],
				-100 => [ "key" => "cancelled" ],
			],
			"date_format" => "d/m/Y",
			"time_format" => "g:i A",
			"metric" => [ 'KG' ],
    	];
    }

    /**
	 *	Update initial variables
	 */
    public function update_initial()
    {
    	global $wpdb;

    	$this->appid = strtolower( $this->plugin_ref['id'] );
		$this->prefix = $wpdb->prefix.strtolower( $this->plugin_ref['id']."_" );
		$this->debug = ( $this->setting && $this->setting['debug'] )? true : $this->debug;
		
		$this->plugin_ref['setting'] = $setting = $this->get_setting();
		if( $def_date_format = get_option( 'date_format' ) ) $this->plugin_ref['date_format'] = $def_date_format;
		if( $def_time_format = get_option( 'time_format' ) ) $this->plugin_ref['time_format'] = $def_time_format;

		$general = $setting['general'];

		if( ! $general['use_asset'] ) $this->off_menu( 'wh_asset' );
		if( ! $general['use_vending_machine'] ) $this->off_menu( 'wh_vending_machine' );
		if( ! $general['use_brand'] ) $this->off_menu( 'wh_brand' );
		if( ! $general['use_item_storing_type'] ) $this->off_menu( 'wh_items', 'wh_items_store_type' );
		if( ! $general['use_uom_conversion'] ) $this->off_menu( 'wh_items', 'wh_uom_conversion' );
		if( ! $general['use_reprocess_item'] ) 
		{
			$this->off_menu( 'wh_items', 'wh_reprocess_item' );
			$this->off_menu( 'wh_inventory', 'wh_reprocess', 'branch_menu' );
		}
		if( ! $general['use_itemize'] ) $this->off_menu( 'wh_items', 'wh_itemize' );
		if( ! $general['use_price_margin'] ) $this->off_menu( 'wh_pricing', 'wh_pricing_margin' );
		if( ! $general['use_promo'] ) $this->off_menu( 'wh_pricing', 'wh_promo' );
		if( ! $general['use_customer'] ) $this->off_menu( 'wh_customer' );
		if( ! $general['use_credit'] ) $this->off_menu( 'wh_credit' );
		if( ! $general['use_payment_method'] ) $this->off_menu( 'wh_other_mst', 'wh_payment_method' );
		if( ! $general['use_payment_term'] ) $this->off_menu( 'wh_other_mst', 'wh_payment_term' );

		if( ! $general['use_margining'] ) $this->off_menu( 'wh_margining' );

		//reports
		$mandatory_rpt = [ 
			'wh_reports', 
			'wh_pos_rpt', 
			'wh_pos_cost_rpt',
			'wh_purchase_rpt', 
			'wh_sales_rpt', 
			'wh_discrepancy_rpt',
			'wh_inventory_rpt',
			'wh_movement_rpt',
			'wh_inout_rpt',
			'wh_stock_aging_rpt',
			'wh_balance_rpt', 
			'wh_reorder_rpt',
			'wh_unprocessed_doc_rpt', 
			'wh_hr_rpt',
			'wh_trade_in_rpt',
			'wh_intercom_company_rpt',
			'wh_intercom_worker_rpt'
		];
		$rpts = [ 'wh_reports', 'wh_customer_rpt', 'wh_pos_rpt', 'wh_purchase_rpt', 'wh_sales_rpt', 'wh_inventory_rpt', 'wh_hr_rpt' ];
		foreach( $rpts as $rpt )
		{
			foreach( $this->plugin_ref['menu'][ $rpt ] as $key => $vals )
			{
				if( in_array( $key, $mandatory_rpt ) ) continue;
				if( ! $general['use_report'][ $key ] ) $this->off_menu( $rpt, $key );
			}
		}
		
		
		//charts
		$mandatory_chart = [ 
			'wh_charts', 
			'wh_pos_overall_chart',
			'wh_pos_chart', 
		];
		foreach( $this->plugin_ref['menu']['wh_charts'] as $key => $vals )
		{
			if( in_array( $key, $mandatory_chart ) ) continue;
			if( ! $general['use_chart'][ $key ] ) $this->off_menu( 'wh_charts', $key );
		}
    }
    	public function off_menu( $sect = '', $sub = '', $menu = 'menu' )
    	{
    		if( ! $sect || ! $menu ) return;

    		if( $this->plugin_ref[ $menu ][ $sect ] )
    		{	
    			if( ! empty( $sub ) )
    			{
    				$this->plugin_ref[ $menu ][ $sect ][ $sub ]['off'] = true;
    			}
    			else
    			{
    				foreach( $this->plugin_ref[ $menu ][ $sect ] as $k => $m )
		    		{
						$this->plugin_ref[ $menu ][ $sect ][ $k ]['off'] = true;
		    		}
    			}
    		}
    	}
	
	/**
	 *	Defining constants
	 */
	public function define_constants()
	{
        $this->define( "WCWH_VERSION", $this->plugin_ref['version'] );
        $this->define( "WCWH_APPID", $this->appid );
		$this->define( "WCWH_PLUGIN_URL", $this->plugin_url() );
		$this->define( "WCWH_DIR", untrailingslashit( plugin_dir_path(__FILE__) ) );
		$this->define( "WCWH_AJAX_URL", WCWH_PLUGIN_URL."/reception.php" );
    }
	
	/**
	 *	Initialize hooks
	 */
	public function init_hooks()
	{
		$refs = $this->get_plugin_ref();
		add_action( 'plugins_loaded', array( $this, 'wpdb_table_add' ) );

		//Change Design
		include_once( WCWH_DIR . "/design.php" );

		//General functions
		include_once( WCWH_DIR . "/includes/notices.php" );
		include_once( WCWH_DIR . "/includes/crud_controller.php" );
		include_once( WCWH_DIR . "/includes/tree.php" );
		include_once( WCWH_DIR . "/includes/document.php" );
		include_once( WCWH_DIR . "/includes/listing.php" );
		include_once( WCWH_DIR . "/includes/email.php" );
		include_once( WCWH_DIR . "/includes/functions.php" );
		include_once( WCWH_DIR . "/includes/hooks.php" );
		
		//Plugin activation / deactivation
		register_activation_hook( __FILE__, array( $this, "plugin_activation" ) );
		register_deactivation_hook( __FILE__, array( $this, "plugin_deactivation" ) );
		
		//Admin scripts
		add_action( "admin_enqueue_scripts", array( $this, "enqueue_admin" ) );
		add_action( "wp_enqueue_scripts", array( $this, "enqueue_admin" ) );
		
		//Plugin Init
		//add_action( 'init', array( $this, "redirect_to_backend" ), 1 );
		add_action( "init", array( $this, "permission_alter" ), 1 );
		add_action( "admin_init", array( $this, "multisite_permission_correction" ), 999 );
		add_action( "wcwh_page_init", array( $this, "multisite_permission_correction" ), 999 );
		add_action( "init", array( $this, "plugin_init" ), 9 );

		//Backend menu
		add_action( "admin_menu", array( $this, "admin_create_menu" ) );
		add_action( "admin_menu", array( $this, "menu_access_right" ), 999 );

		//others
		add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widgets' ) );
		add_filter( 'admin_title', array( $this, 'admin_title' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'multisite_admin_bar' ) );

		add_filter( 'media_upload_tabs', array( $this, 'media_upload_tabs' ), 10, 1 );
	}

	/**
	 *	Meta - set table names.
	 */
	public function wpdb_table_add()
	{
		global $wpdb, $wcwh;
		$prefix = $wcwh->prefix;
		$wpdb->companymeta = $prefix.'companymeta';
		$wpdb->warehousemeta = $prefix.'warehousemeta';
		$wpdb->suppliermeta = $prefix.'suppliermeta';
		$wpdb->clientmeta = $prefix.'clientmeta';
		$wpdb->vending_machinemeta = $prefix.'vending_machinemeta';
		$wpdb->brandmeta = $prefix.'brandmeta';
		$wpdb->storagemeta = $prefix.'storagemeta';
		$wpdb->itemsmeta = $prefix.'itemsmeta';
		$wpdb->itemizemeta = $prefix.'itemizemeta';
		$wpdb->customermeta = $prefix.'customermeta';
		$wpdb->pricingmeta = $prefix.'pricingmeta';
		$wpdb->promo_headermeta = $prefix.'promo_headermeta';
		$wpdb->membermeta = $prefix.'membermeta';
		$wpdb->member_transactmeta = $prefix.'member_transactmeta';
		
		$wpdb->doc_header = $prefix.'document';
		$wpdb->doc_detail = $prefix.'document_items';
		$wpdb->doc_meta = $prefix.'document_meta';

		$wpdb->transaction_meta = $prefix.'transaction_meta';

		$wpdb->assetmeta = $prefix.'assetmeta';
		$wpdb->items_meta_rel = $prefix.'items_meta_rel';

		$wpdb->scheme = $prefix.'scheme';

		$wpdb->section = $prefix.'section';

		$this->plugin_ref['wpdb_tables'] = array(
			'companymeta' => $wpdb->companymeta,
			'warehousemeta' => $wpdb->warehousemeta,
			'suppliermeta' => $wpdb->suppliermeta,
			'clientmeta' => $wpdb->clientmeta,
			'vending_machinemeta' => $wpdb->vending_machinemeta,
			'brandmeta' => $wpdb->brandmeta,
			'storagemeta' => $wpdb->storagemeta,
			'itemsmeta' => $wpdb->itemsmeta,
			'itemizemeta' => $wpdb->itemizemeta,
			'customermeta' => $wpdb->customermeta,
			'pricingmeta' => $wpdb->pricingmeta,
			'promo_headermeta' => $wpdb->promo_headermeta,
			'membermeta' => $wpdb->membermeta,
			'member_transactmeta' => $wpdb->member_transactmeta,
			'doc_header' => $wpdb->doc_header,
			'doc_detail' => $wpdb->doc_detail,
			'doc_meta' => $wpdb->doc_meta,
			'assetmeta' => $wpdb->assetmeta,
			'transaction_meta' => $wpdb->transaction_meta,
			'items_meta_rel' => $wpdb->items_meta_rel,
			'scheme' => $wpdb->scheme,
			'section' => $wpdb->section,
		);

		$sections = get_sections();
		if( $sections )
		{
			$sect = array();
			foreach( $sections as $i => $section )
			{
				$sect[ $section['section_id'] ] = $section;
			}
		}
		$this->plugin_ref['sections'] = $sect;
	}
	
	/**
	 *	Admin_scripts enqueue style and script
	 */
	public function enqueue_admin()
	{	
		$suffix = ( defined( 'WCWH_DEBUG' ) && WCWH_DEBUG )? '' : '';
		
		wp_enqueue_style( "wcwh-select2-style", WCWH_PLUGIN_URL . "/assets/css/select2{$suffix}.css", array(), WCWH_VERSION );
		wp_enqueue_style( "bootstrap-style", WCWH_PLUGIN_URL . "/assets/css/bootstrap{$suffix}.css", array(), WCWH_VERSION );
		//wp_register_style( "mdb-style", WCWH_PLUGIN_URL . "/assets/css/mdb{$suffix}.css", array(), WCWH_VERSION );
		wp_enqueue_style( "font-awesome", WCWH_PLUGIN_URL . "/assets/css/font-awesome{$suffix}.css", array(), WCWH_VERSION );
		wp_enqueue_style( "jquery-ui", WCWH_PLUGIN_URL . "/assets/css/jquery-ui{$suffix}.css", array(), WCWH_VERSION );
		wp_enqueue_style( "wcwh-main-style", WCWH_PLUGIN_URL . "/assets/css/wh-style{$suffix}.css", array(), WCWH_VERSION );
		wp_enqueue_style( "datepicker", WCWH_PLUGIN_URL . "/assets/css/bootstrap-datepicker{$suffix}.css", array(), WCWH_VERSION );

		//wp_enqueue_script( 'jquery.min', WCWH_PLUGIN_URL . "/assets/js/jquery{$suffix}.js", array(), WCWH_VERSION );
		//wp_enqueue_script( 'datedropper', WCWH_PLUGIN_URL . "/assets/js/datedropper.pro{$suffix}.js", array('jquery'), WCWH_VERSION );
		
		wp_register_script( 'datepicker', WCWH_PLUGIN_URL . "/assets/js/bootstrap-datepicker{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'popper.min', WCWH_PLUGIN_URL . "/assets/js/popper{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'bootstrap.min', WCWH_PLUGIN_URL . "/assets/js/bootstrap{$suffix}.js", array('jquery'), WCWH_VERSION );
		//wp_register_script( 'mdb.min', WCWH_PLUGIN_URL . "/assets/js/mdb{$suffix}.js", array('jquery'), WCWH_VERSION );

		wp_register_script( 'jquery_blockUI', WCWH_PLUGIN_URL . "/assets/js/jquery.blockUI{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'detect-agent', WCWH_PLUGIN_URL . "/assets/js/detect{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'jquery_select2', WCWH_PLUGIN_URL . "/assets/js/select2{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'jquery-ui', WCWH_PLUGIN_URL . "/assets/js/jquery-ui{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'jquery_validate', WCWH_PLUGIN_URL . "/assets/js/jquery.validate{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'jquery_numeric', WCWH_PLUGIN_URL . "/assets/js/jquery.numeric{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'jquery_barcodelistener', WCWH_PLUGIN_URL . "/assets/js/anysearch{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'wc-pos-js-barcode', WCWH_PLUGIN_URL . "/assets/js/JsBarcode.all{$suffix}.js", array('jquery'), WCWH_VERSION );
        wp_register_script( 'wc-pos-js-qr-code', WCWH_PLUGIN_URL . "/assets/js/qrcode{$suffix}.js", array('jquery'), WCWH_VERSION );
        wp_register_script( 'qr-scan', WCWH_PLUGIN_URL . "/assets/js/html5-qrcode{$suffix}.js", array('jquery'), WCWH_VERSION );
        wp_register_script( 'chartjs', WCWH_PLUGIN_URL . "/assets/js/Chart{$suffix}.js", array('jquery'), WCWH_VERSION );
        wp_register_script( 'randcolor', WCWH_PLUGIN_URL . "/assets/js/randomcolor{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'wcwh-main-scripts', WCWH_PLUGIN_URL . "/assets/js/wh-script{$suffix}.js", array('jquery'), WCWH_VERSION );
		wp_register_script( 'custom-script', WCWH_PLUGIN_URL . "/assets/js/custom-script{$suffix}.js", array('jquery'), WCWH_VERSION );
		
		$user_caps = array();
		$user_id = 0;
		if( is_user_logged_in() )
		{
			$user_id = get_current_user_id();
			$data = get_userdata( $user_id );
			if ( is_object( $data) ) $current_user_caps = $data->allcaps;
			$current_user_caps = array_keys( $current_user_caps );
			foreach( $current_user_caps as $cap )
			{
				if( in_array( $cap, $this->plugin_ref['capabilities'] ) )
					$user_caps[] = $cap;
			}
		}
     
		$ajax_object = array( 
			'ajax_url'		=> WCWH_PLUGIN_URL."/reception.php",
			//'ajax_url'		=> admin_url( 'admin-ajax.php' ),
			'appid'			=> $this->appid,
			'capabilities'	=> $user_caps,
			'user_id'		=> $user_id,
			'debug'			=> ( defined( 'WCWH_DEBUG' ) && WCWH_DEBUG )? false : false,
			'float_timer'		=> 7, // milisec
			'iserial_length'	=> 9,
			'iserial_match'		=> 19,
			'iserial_weight'	=> 4,
			'iweight_dividen'	=> 100,
			'ibatch_match'		=> 15,
			'ibatch_length'		=> 9,
		);
		wp_localize_script( 'wcwh-main-scripts', 'ajax_wh', $ajax_object );
	}

	/**
	 *	Permission Altering
	 */
	public function permission_alter()
	{	
		global $current_user, $wcwh;

		if( ! $current_user ) return false;

		//pd( $current_user, true, true );
		$user_role = $current_user->roles;
		$user_id = $current_user->ID;
		$allcaps = $current_user->allcaps;
		
		if( ! class_exists( 'WCWH_Permission_Class' ) ) include_once( WCWH_DIR . "/includes/classes/permission.php" );
		$Inst = new WCWH_Permission_Class();

		foreach( $user_role as $i => $_user_role )
		{
			$results = $Inst->get_permission( $_user_role, $user_id, 'max', true );

			if( $results )
			{
				$permissions = is_json( $results['permission'] )? json_decode( $results['permission'], true ) : array();

				if( $permissions )
				{
					foreach( $permissions as $i => $row )
					{
						$allcaps[ $row ] = 1;
					}
				}
			}
		}
		
		$wcwh->caps = $allcaps;
		$current_user->allcaps = $allcaps;
	}
	/**
	 *	Permission Altering for multisite
	 */
	public function multisite_permission_correction()
	{
		if( is_multisite() )
		{
			global $current_user, $wcwh;
			
			if( $wcwh->caps && ( count( $current_user->allcaps ) ) != count( $wcwh->caps ) ) 
				$current_user->allcaps = $wcwh->caps;
		}
	}

	/**
	 *	Plugin initialize
	 */
	public function plugin_init()
	{
		$refs = $this->get_plugin_ref();

		if( $this->debug )
		{
			if( current_user_can( 'manage_options' ) && current_user_can( 'wh_can_debug' ) )
				$this->define( "WCWH_DEBUG", true );
		}

		include_once( WCWH_DIR . "/includes/files.php" );
		
		include_once( WCWH_DIR . "/includes/classes/transaction.php" );
		include_once( WCWH_DIR . "/includes/classes/stocks.php" );
		
		include_once( WCWH_DIR . "/includes/classes/todo.php" );

		include_once( WCWH_DIR . "/includes/classes/asset-movement.php" );

		include_once( WCWH_DIR . "/includes/classes/pos-credits.php" );

		include_once( WCWH_DIR . "/includes/classes/remote_response.php" );

		//Ajax
		include_once( WCWH_DIR . "/includes/ajax.php" );

		if( current_user_cans( ['access_wh_todo'] ) ) include_once( WCWH_DIR . "/includes/ajax/todoAjax.php" );

		if( current_user_cans( ['access_wh_company'] ) ) include_once( WCWH_DIR . "/includes/ajax/companyAjax.php" );

		if( current_user_cans( ['access_wh_warehouse'] ) ) include_once( WCWH_DIR . "/includes/ajax/warehouseAjax.php" );

		if( current_user_cans( ['access_wh_supplier'] ) ) include_once( WCWH_DIR . "/includes/ajax/supplierAjax.php" );

		if( current_user_cans( ['access_wh_client'] ) ) include_once( WCWH_DIR . "/includes/ajax/clientAjax.php" );

		if( current_user_cans( ['access_wh_vending_machine'] ) ) include_once( WCWH_DIR . "/includes/ajax/vendingMachineAjax.php" );

		if( current_user_cans( ['access_wh_brand'] ) ) include_once( WCWH_DIR . "/includes/ajax/brandAjax.php" );
		if( current_user_cans( ['access_wh_criteria'] ) ) include_once( WCWH_DIR . "/includes/ajax/criteriaAjax.php" );

		if( current_user_cans( ['access_wh_asset'] ) ) include_once( WCWH_DIR . "/includes/ajax/assetAjax.php" );

		if( current_user_cans( ['access_wh_items'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemAjax.php" );
		if( current_user_cans( ['access_wh_items_group'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemGroupAjax.php" );
		if( current_user_cans( ['access_wh_items_store_type'] ) ) include_once( WCWH_DIR . "/includes/ajax/storeTypeAjax.php" );
		if( current_user_cans( ['access_wh_items_category'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemCategoryAjax.php" );
		if( current_user_cans( ['access_wh_uom'] ) ) include_once( WCWH_DIR . "/includes/ajax/uomAjax.php" );
		if( current_user_cans( ['access_wh_uom_conversion'] ) ) include_once( WCWH_DIR . "/includes/ajax/uomConversionAjax.php" );
		if( current_user_cans( ['access_wh_reprocess_item'] ) ) include_once( WCWH_DIR . "/includes/ajax/reprocessItemAjax.php" );
		if( current_user_cans( ['access_wh_itemize'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemizeAjax.php" );
		if( current_user_cans( ['access_wh_items_order_type'] ) ) include_once( WCWH_DIR . "/includes/ajax/orderTypeAjax.php" );
		if( current_user_cans( ['access_wh_items_relation'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemRelAjax.php" );
		if( current_user_cans( ['access_wh_item_scan'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemScanAjax.php" );

		//Item Expiry
		if( current_user_cans( ['access_wh_item_expiry'] ) ) include_once( WCWH_DIR . "/includes/ajax/itemExpiryAjax.php" );

		if( current_user_cans( ['access_wh_pricing'] ) ) include_once( WCWH_DIR . "/includes/ajax/pricingAjax.php" );
		if( current_user_cans( ['access_wh_margin'] ) ) include_once( WCWH_DIR . "/includes/ajax/marginAjax.php" );
		if( current_user_cans( ['access_wh_purchase_pricing'] ) ) include_once( WCWH_DIR . "/includes/ajax/purchasePricingAjax.php" );
		include_once( WCWH_DIR . "/includes/ajax/promoAjax.php" );

		if( current_user_cans( ['access_wh_origin_group'] ) ) include_once( WCWH_DIR . "/includes/ajax/originGroupAjax.php" );
		if( current_user_cans( ['access_wh_account_type'] ) ) include_once( WCWH_DIR . "/includes/ajax/accountTypeAjax.php" );
		if( current_user_cans( ['access_wh_customer_group'] ) ) include_once( WCWH_DIR . "/includes/ajax/customerGroupAjax.php" );
		if( current_user_cans( ['access_wh_customer_job'] ) ) include_once( WCWH_DIR . "/includes/ajax/customerJobAjax.php" );
		if( current_user_cans( ['access_wh_customer'] ) ) include_once( WCWH_DIR . "/includes/ajax/customerAjax.php" );

		if( current_user_cans( ['access_wh_credit_term'] ) ) include_once( WCWH_DIR . "/includes/ajax/creditTermAjax.php" );
		if( current_user_cans( ['access_wh_credit'] ) ) include_once( WCWH_DIR . "/includes/ajax/creditAjax.php" );
		if( current_user_cans( ['access_wh_credit_topup'] ) ) include_once( WCWH_DIR . "/includes/ajax/creditTopupAjax.php" );
		if( current_user_cans( ['access_wh_payment_method'] ) ) include_once( WCWH_DIR . "/includes/ajax/paymentMethodAjax.php" );
		if( current_user_cans( ['access_wh_payment_term'] ) ) include_once( WCWH_DIR . "/includes/ajax/paymentTermAjax.php" );

		if( current_user_cans( ['access_wh_membership'] ) ) include_once( WCWH_DIR . "/includes/ajax/membershipAjax.php" );
		if( current_user_cans( ['access_wh_member_topup'] ) ) include_once( WCWH_DIR . "/includes/ajax/memberTopupAjax.php" );

		//Bank In Service
		if( current_user_cans( ['access_wh_bankin_service'] ) ) include_once( WCWH_DIR . "/includes/ajax/bankinserviceAjax.php" );
		if( current_user_cans( ['access_wh_bankin_collector'] ) ) include_once( WCWH_DIR . "/includes/ajax/bankinCollectorAjax.php" );
		if( current_user_cans( ['access_wh_bankin_info'] ) ) include_once( WCWH_DIR . "/includes/ajax/bankininfoAjax.php" );
		if( current_user_cans( ['access_wh_exchange_rate'] ) ) include_once( WCWH_DIR . "/includes/ajax/exchangeRateAjax.php" );
		if( current_user_cans( ['access_wh_service_charge'] ) ) include_once( WCWH_DIR . "/includes/ajax/servicechargeAjax.php" );

		//Tool Requisition
		include_once( WCWH_DIR . "/includes/ajax/toolRequestAjax.php" );
		if( current_user_cans( ['access_wh_tool_request_rpt'] ) ) include_once( WCWH_DIR . "/includes/reports/toolRequestReportAjax.php" );
		if( current_user_cans( ['access_wh_tool_request_fulfilment'] ) ) include_once( WCWH_DIR . "/includes/reports/toolRequestFulfilmentAjax.php" );

		//Parts Requisition
		include_once( WCWH_DIR . "/includes/ajax/partsRequestAjax.php" );

		if( current_user_cans( ['wh_manage_permission'] ) ) include_once( WCWH_DIR . "/includes/ajax/permissionAjax.php" );
		if( current_user_cans( ['wh_manage_role'] ) ) include_once( WCWH_DIR . "/includes/ajax/roleAjax.php" );

		if( current_user_cans( ['wh_configure'] ) )
		{
			include_once( WCWH_DIR . "/includes/ajax/sectionAjax.php" );
			include_once( WCWH_DIR . "/includes/ajax/schemeAjax.php" );
			include_once( WCWH_DIR . "/includes/ajax/statusAjax.php" );
			include_once( WCWH_DIR . "/includes/ajax/stockoutAjax.php" );
			include_once( WCWH_DIR . "/includes/ajax/runningNoAjax.php" );

			include_once( WCWH_DIR . "/includes/ajax/todoArrangementAjax.php" );
			include_once( WCWH_DIR . "/includes/ajax/todoActionAjax.php" );

			include_once( WCWH_DIR . "/includes/ajax/templateAjax.php" );
		}

		if( current_user_cans( ['wh_maintain_user'] ) ) include_once( WCWH_DIR . "/includes/ajax/userAjax.php" );

		//Branch 	
		if( current_user_cans( ['access_wh_inventory'] ) ) include_once( WCWH_DIR . "/includes/ajax/inventoryAjax.php" );
		if( current_user_cans( ['access_wh_purchase_request'] ) ) include_once( WCWH_DIR . "/includes/ajax/purchaseRequestAjax.php" );
		///------------jeff----------------//
		if( current_user_cans( ['access_wh_remote_cpr'] ) ) include_once( WCWH_DIR . "/includes/ajax/remoteCPRAjax.php" );
		if( current_user_cans( ['access_wh_ordering_pr'] ) ) include_once( WCWH_DIR . "/includes/ajax/orderingPRAjax.php" );
		if( current_user_cans( ['access_wh_closing_pr'] ) ) include_once( WCWH_DIR . "/includes/ajax/closingPRAjax.php" );
		/////////--------jeff------------------///////////////
		if( current_user_cans( ['access_wh_purchase_order'] ) ) include_once( WCWH_DIR . "/includes/ajax/purchaseOrderAjax.php" );
		if( current_user_cans( ['access_wh_purchase_cdnote'] ) ) include_once( WCWH_DIR . "/includes/ajax/purchaseCDNoteAjax.php" );
		if( current_user_cans( ['access_wh_self_bill'] ) ) include_once( WCWH_DIR . "/includes/ajax/selfBillAjax.php" );

		if( current_user_cans( ['access_wh_sales_order'] ) ) include_once( WCWH_DIR . "/includes/ajax/saleOrderAjax.php" );
		if( current_user_cans( ['access_wh_sales_return'] ) ) include_once( WCWH_DIR . "/includes/ajax/saleReturnAjax.php" );
		if( current_user_cans( ['access_wh_sale_cdnote'] ) ) include_once( WCWH_DIR . "/includes/ajax/saleCDNoteAjax.php" );
		if( current_user_cans( ['access_wh_e_invoice'] ) ) include_once( WCWH_DIR . "/includes/ajax/eInvoiceAjax.php" );
		
		if( current_user_cans( ['access_wh_transfer_order'] ) ) include_once( WCWH_DIR . "/includes/ajax/transferOrderAjax.php" );
		if( current_user_cans( ['access_wh_good_issue'] ) ) include_once( WCWH_DIR . "/includes/ajax/goodIssueAjax.php" );
		if( current_user_cans( ['access_wh_issue_return'] ) ) include_once( WCWH_DIR . "/includes/ajax/issueReturnAjax.php" );
		if( current_user_cans( ['access_wh_reprocess'] ) ) include_once( WCWH_DIR . "/includes/ajax/reprocessAjax.php" );
		if( current_user_cans( ['access_wh_delivery_order'] ) ) include_once( WCWH_DIR . "/includes/ajax/deliveryOrderAjax.php" );
		if( current_user_cans( ['access_wh_good_receive'] ) ) include_once( WCWH_DIR . "/includes/ajax/goodReceiveAjax.php" );
		if( current_user_cans( ['access_wh_good_return'] ) ) include_once( WCWH_DIR . "/includes/ajax/goodReturnAjax.php" );
		if( current_user_cans( ['access_wh_stock_adjust'] ) ) include_once( WCWH_DIR . "/includes/ajax/adjustmentAjax.php" );
		if( current_user_cans( ['access_wh_stocktake'] ) ) include_once( WCWH_DIR . "/includes/ajax/stocktakeAjax.php" );
		if( current_user_cans( ['access_wh_block_stock'] ) ) include_once( WCWH_DIR . "/includes/ajax/blockStockAjax.php" );
		if( current_user_cans( ['access_wh_block_action'] ) ) include_once( WCWH_DIR . "/includes/ajax/blockActionAjax.php" );
		if( current_user_cans( ['access_wh_transfer_item'] ) ) include_once( WCWH_DIR . "/includes/ajax/transferItemAjax.php" );
		if( current_user_cans( ['access_wh_pos_transact'] ) ) include_once( WCWH_DIR . "/includes/ajax/posTransactAjax.php" );
		if( current_user_cans( ['access_wh_do_revise'] ) ) include_once( WCWH_DIR . "/includes/ajax/DOReviseAjax.php" );

		if( current_user_cans( ['access_wh_storage'] ) ) include_once( WCWH_DIR . "/includes/ajax/storageAjax.php" );
		if( current_user_cans( ['access_wh_acc_period'] ) ) include_once( WCWH_DIR . "/includes/ajax/accPeriodAjax.php" );
		if( current_user_cans( ['access_wh_stocktake_close'] ) ) include_once( WCWH_DIR . "/includes/ajax/stockTakeCloseAjax.php" );

		if( current_user_cans( ['access_wh_stock_movement_rectify'] ) ) include_once( WCWH_DIR . "/includes/ajax/smRectifyAjax.php" );

		//margining
		if( current_user_cans( ['access_wh_margining'] ) ) include_once( WCWH_DIR . "/includes/ajax/marginingAjax.php" );

		if( current_user_cans( ['access_wh_search_tin'] ) ) include_once( WCWH_DIR . "/includes/ajax/searchTinAjax.php" );
		
		//Reports 	
		if( current_user_cans( [ 'view_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/queryReportAjax.php" );

		if( current_user_cans( [ 'access_customer_purchases_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/customerPurchasesAjax.php" );
		if( current_user_cans( [ 'access_credit_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/customerCreditAjax.php" );
		if( current_user_cans( [ 'access_tool_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/customerToolAjax.php" );
		if( current_user_cans( [ 'access_receipt_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/receiptCountAjax.php" );

		if( current_user_cans( [ 'access_pos_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/posSalesAjax.php" );
		if( current_user_cans( [ 'access_pos_cost_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/posCostAjax.php" );

		if( current_user_cans( [ 'access_purchase_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/purchaseReportAjax.php" );

		//Bank In Service
		if( current_user_cans( [ 'access_wh_bankin_service_rpt', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/bankInReportAjax.php" );
		
		if( current_user_cans( [ 'access_sales_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/salesReportAjax.php" );
		if( current_user_cans( [ 'access_momawater_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/momawaterAjax.php" );
		if( current_user_cans( [ 'access_discrepancy_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/discrepancyAjax.php" );
		if( current_user_cans( [ 'access_stock_aging_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/stockAgingAjax.php" );

		if( current_user_cans( [ 'access_transaction_log_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/ajax/transactionLogAjax.php" );

		if( current_user_cans( [ 'access_movement_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/stockMovementAjax.php" );
		if( current_user_cans( [ 'access_inout_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/stockInOutAjax.php" );
		if( current_user_cans( [ 'access_balance_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/stockBalanceAjax.php" );
		if( current_user_cans( [ 'access_itemize_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/itemizeReportAjax.php" );
		if( current_user_cans( [ 'access_reorder_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/reorderReportAjax.php" );
		if( current_user_cans( [ 'access_unprocessed_doc_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/unprocessDocAjax.php" );

		if( current_user_cans( [ 'access_foodboard_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/foodBoardAjax.php" );
		if( current_user_cans( [ 'access_estate_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/estateAjax.php" );
		if( current_user_cans( [ 'access_estate_expenses_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/estateExpensesAjax.php" );
		if( current_user_cans( [ 'access_et_price_wh_reports', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/etPricingAjax.php" );
		if( current_user_cans( [ 'access_trade_in_wh_reports' ] ) )
			include_once( WCWH_DIR . "/includes/reports/tradeInAjax.php" );

		if( current_user_cans( [ 'access_intercom_company_wh_reports', 'access_intercom_worker_wh_reports', 'wh_can_debug' ] ) )
			include_once( WCWH_DIR . "/includes/reports/intercomAjax.php" );

		//Charts
		if( current_user_cans( [ 'view_wh_charts', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/charts/queryChartAjax.php" );
		if( current_user_cans( [ 'access_pos_overall_wh_charts', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/charts/posOverallChartAjax.php" );
		if( current_user_cans( [ 'access_pos_wh_charts', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/charts/posSalesChartAjax.php" );
		if( current_user_cans( [ 'access_foodboard_wh_charts', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/charts/foodBoardChartAjax.php" );
		if( current_user_cans( [ 'access_estate_wh_charts', 'wh_can_debug' ] ) ) 
			include_once( WCWH_DIR . "/includes/charts/estateChartAjax.php" );

		//others
		if( current_user_cans( [ 'view_register' ] ) )
			include_once( WCWH_DIR . "/includes/ajax/posSessionAjax.php" );
		if( current_user_cans( [ 'wh_support' ] ) )
			include_once( WCWH_DIR . "/includes/ajax/posPriceAjax.php" );
		if( current_user_cans( [ 'wh_support', 'manage_pos_order' ] ) )
			include_once( WCWH_DIR . "/includes/ajax/posOrderAjax.php" );
		if( current_user_cans( [ 'wh_support', 'manage_pos_order' ] ) )
			include_once( WCWH_DIR . "/includes/ajax/posCDNAjax.php" );
		if( current_user_cans( [ 'access_wh_pos_do' ] ) ) 
			include_once( WCWH_DIR . "/includes/ajax/posDOAjax.php" );
		if( current_user_cans( ['wh_support'] ) ) 
			include_once( WCWH_DIR . "/includes/ajax/posCreditAjax.php" );

		///////////////////------jeff--------------------------//////////
		if( current_user_cans( ['access_wh_simplify_reprocess'] ) ) include_once( WCWH_DIR . "/includes/ajax/reprocessAjaxv2.php" );
		/////////--------jeff------------------///////////////
		
		//--------22/11/2022 Repleaceable
		if( current_user_cans( [ 'access_wh_repleaceable' ] ) ) 
			include_once( WCWH_DIR . "/includes/ajax/repleaceableAjax.php" );
		//--------22/11/2022 Repleaceable

		//cash withdrawal
		if( current_user_cans( [ 'access_wh_pos_cash_withdrawal' ] ) ) 
			include_once( WCWH_DIR . "/includes/ajax/posCashWithdrawalAjax.php" );
		// money collector
		if( current_user_cans( [ 'access_wh_money_collector' ] ) ) 
		include_once( WCWH_DIR . "/includes/ajax/moneyCollectorAjax.php" );
		//uncollected money 
		if( current_user_cans( [ 'access_wh_uncollected_money_rpt' ] ) ) 
			include_once( WCWH_DIR . "/includes/reports/uncollectedMoneyAjax.php" );
		//task schedule
		if( current_user_cans( ['access_wh_task_schedule'] ) ) include_once( WCWH_DIR . "/includes/ajax/taskScheduleAjax.php" );
		if( current_user_cans( ['access_wh_task_checklist'] ) ) include_once( WCWH_DIR . "/includes/ajax/taskChecklistAjax.php" );
	}
	
	/**
	 *	Backend_menu create menu
	 */
	public function admin_create_menu()
	{
		//Backend pages
		include_once( "includes/pages.php" );

		//Warehouse / Branch Menu
		$warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [], false, [ 'company'=>1 ] );
		$multi = sizeof( $warehouses );
		
		$menus = $this->plugin_ref["menu"];
		$this->register_menu( $menus );

		if( $multi >= 1 )
		{
			$menus = $this->plugin_ref["branch_menu"];

			$a = 0;
			foreach( $warehouses as $i => $wh )
			{
				$wh_menus = array();
				$wh_cap = ( !empty( $wh['capability'] ) && is_json( $wh['capability'] ) )? json_decode( $wh['capability'], true ) : array();
				
				$wh_code = strtolower( $wh['code'] );
				$prefix = $wh_code."-";

				$b = 0;
				foreach( $menus as $key_id => $menu )
				{
					$wh_key = "";
					$wh_sub = array(); 
					$c = 0;
					
					foreach( $menu as $menu_id => $args )
					{
						if( empty( $args['cap'] ) || ( !empty( $args['cap'] ) && item_in_array( $args['cap'], $wh_cap ) ) )
						{
							$wh_menu_id = $prefix.$menu_id;
							if( $c == 0 )
							{
								$wh_key = $wh_menu_id;
								$args['main_title'] = $wh['name'];
							}

							$args['section'] = $menu_id;
							$args['page_title'] = $args['title']." - ".strtoupper( $wh['code'] );

							$args['order'] = (string)str_replace( "{i}", $a, $args['order'] );

							$wh_sub[$wh_menu_id] = $args;

							$c++;
						}
					}
					$wh_menus[$wh_key] = $wh_sub;
					$b++;
				}
				
				$this->register_menu( $wh_menus, $wh );
				$a++;
			}
		}
	}

	/**
	 *	Register Menu Item to Wordpress
	 */
	public function register_menu( $menus = array(), $wh = array() )
	{
		if( ! $menus ) return;

		foreach( $menus as $key_id => $menu )
		{
			$c = 0;
			foreach( $menu as $menu_id => $args )
			{
				if( $args['off'] ) continue;

				$Inst = new WCWH_Pages();
				$page_id = !empty( $args['id'] )? $args['id'] : $menu_id;
				$Inst->set_page_id( $page_id );
				if( $args['description'] )$Inst->set_description( $args['description'] );

				$section = !empty( $args['section'] )? $args['section'] : $page_id;
				$Inst->set_section( $section );
				
				if( $wh )
				{
					$Inst->set_warehouse( $wh );
					$permissions = get_warehouse_meta( $wh['id'], 'permissions', true );
					$permissions = ( !empty( $permissions ) && is_json( $permissions ) )? json_decode( $permissions, true ) : array();
					if( $permissions && ! current_user_cans( $permissions ) ) continue;
				} 

				if( $c == 0 )
				{
					add_menu_page( 
						( $args["main_title"] )? $args["main_title"] : $args["title"], 			//page title
						( $args["main_title"] )? $args["main_title"] : $args["title"], 			//menu title
						$args["capability"],		//capability
						$key_id, 					//key id
						array( $Inst, "page" ), 	//call function
						$args["icon"], 				//icon
						$args["order"] 				//menu order
					);
				}

				if( !empty( $args['tab'] ) )
				{
					$page_id.= (string)"&tab=".$args['tab'];
				}
				
				add_submenu_page( 
					$key_id,					//parent menu id
					( $args["page_title"] )? $args["page_title"] : $args["title"], 			//page title
					$args["title"], 			//menu title
					$args["capability"], 		//capability
					$page_id, 					//menu id
					array( $Inst, "page" ) 		//call function
				);
				$c++;
			}
		}
	}

	/**
	 *	Backend_menu Access Right
	 */
	public function menu_access_right()
	{
		$sys_roles = get_option( 'wcwh_roles' );
		$sys_roles = maybe_unserialize( $sys_roles );

		$plugin_roles = wp_parse_args( $sys_roles,  array_keys( $this->plugin_ref['roles'] ) );
		$plugin_roles = array_unique( $plugin_roles );
		if( ( $key = array_search( 'administrator', $plugin_roles ) ) !== false) {
		    unset( $plugin_roles[$key] );
		}
		
		$user_roles = WCWH_Function::get_user_role();
		
		$wh_roles = array_merge( $plugin_roles, array( 'pos_manager', 'cashier' ) );
		
		$isRole = false;
		if( $user_roles )
		{
			foreach( $user_roles as $role )
			{
				if( in_array( $role, $wh_roles ) ) $isRole = true;
			}
		}

		global $menu, $submenu;
		if( $submenu && $isRole )
		{
			foreach( $submenu as $key => $args )
			{
				if( in_array( $key, $this->plugin_ref['access_menu'] ) )
				{
					remove_menu_page( $key );
				}
			}
		}
		//pd($menu, true);exit;
	}

	public function remove_dashboard_widgets()
	{
		global $wp_meta_boxes;

		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
	    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);
	    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
	    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins']);
	    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_drafts']);
	    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);
	    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
	    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);
	}

	public function admin_title( $final, $title )
	{
		return $title." - ".get_bloginfo( 'name' );
	}

	public function multisite_admin_bar( $wp_admin_bar )
	{
		// Don't show for logged out users or single site mode.
		if ( ! is_user_logged_in() || ! is_multisite() ) return;

		// Show only when the user has at least one site, or they're a super admin.
		if ( count( $wp_admin_bar->user->blogs ) < 1 && ! current_user_can( 'manage_network' ) ) return;

		if ( $wp_admin_bar->user->active_blog ) 
			$my_sites_url = get_admin_url( $wp_admin_bar->user->active_blog->blog_id, 'my-sites.php' );
		else 
			$my_sites_url = admin_url( 'my-sites.php' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'sites',
				'title' => __( 'Sites' ),
				'href'  => $my_sites_url,
			)
		);

		if ( current_user_can( 'manage_network' ) ) 
		{
			$wp_admin_bar->add_group(
				array(
					'parent' => 'sites',
					'id'     => 'sites-super-admin',
				)
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'sites-super-admin',
					'id'     => 'sites-network-admin',
					'title'  => __( 'Network Admin' ),
					'href'   => network_admin_url(),
				)
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'sites-network-admin',
					'id'     => 'sites-network-admin-d',
					'title'  => __( 'Dashboard' ),
					'href'   => network_admin_url(),
				)
			);

			if ( current_user_can( 'manage_sites' ) ) 
			{
				$wp_admin_bar->add_node(
					array(
						'parent' => 'sites-network-admin',
						'id'     => 'sites-network-admin-s',
						'title'  => __( 'Sites' ),
						'href'   => network_admin_url( 'sites.php' ),
					)
				);
			}

			if ( current_user_can( 'manage_network_users' ) ) 
			{
				$wp_admin_bar->add_node(
					array(
						'parent' => 'sites-network-admin',
						'id'     => 'sites-network-admin-u',
						'title'  => __( 'Users' ),
						'href'   => network_admin_url( 'users.php' ),
					)
				);
			}

			if ( current_user_can( 'manage_network_themes' ) ) 
			{
				$wp_admin_bar->add_node(
					array(
						'parent' => 'sites-network-admin',
						'id'     => 'sites-network-admin-t',
						'title'  => __( 'Themes' ),
						'href'   => network_admin_url( 'themes.php' ),
					)
				);
			}

			if ( current_user_can( 'manage_network_plugins' ) ) 
			{
				$wp_admin_bar->add_node(
					array(
						'parent' => 'sites-network-admin',
						'id'     => 'sites-network-admin-p',
						'title'  => __( 'Plugins' ),
						'href'   => network_admin_url( 'plugins.php' ),
					)
				);
			}

			if ( current_user_can( 'manage_network_options' ) ) 
			{
				$wp_admin_bar->add_node(
					array(
						'parent' => 'sites-network-admin',
						'id'     => 'sites-network-admin-o',
						'title'  => __( 'Settings' ),
						'href'   => network_admin_url( 'settings.php' ),
					)
				);
			}
		}

		// Add site links.
		$wp_admin_bar->add_group(
			array(
				'parent' => 'sites',
				'id'     => 'sites-list',
				'meta'   => array(
					'class' => current_user_can( 'manage_network' ) ? 'ab-sub-secondary' : '',
				),
			)
		);
		
		//$current_view = wc_get_current_admin_url();
		//$current_view = str_replace( admin_url(), '', $current_view );
		foreach ( (array) $wp_admin_bar->user->blogs as $blog ) 
		{
			switch_to_blog( $blog->userblog_id );

			$blavatar = '<div class="blavatar"></div>';

			$blogname = $blog->blogname;

			if ( ! $blogname ) 
			{
				$blogname = preg_replace( '#^(https?://)?(www.)?#', '', get_home_url() );
			}

			$menu_id = 'site-' . $blog->userblog_id;

			$wp_admin_bar->add_node(
				array(
					'parent' => 'sites-list',
					'id'     => $menu_id,
					'title'  => $blavatar . $blogname,
					'href'   => admin_url(),
				)
			);

			restore_current_blog();
		}
	}

	public function media_upload_tabs( $_default_tabs )
	{
		unset( $_default_tabs['type_url'] );
		return $_default_tabs;
	}

	/**
	 *	Plugin_activation DB, roles and capabilities
	 */
	public function plugin_activation()
	{
		$refs = $this->get_plugin_ref();
		include_once( "wh-install.php" );
		$inst = new WCWH_Inst( $refs );
		$inst->activation();
		
		flush_rewrite_rules();
	}

	/**
	 *	plugin_deactivation DB, roles and capabilities
	 */
	public function plugin_deactivation()
	{
		$refs = $this->get_plugin_ref();
		include_once( "wh-install.php" );
		$inst = new WCWH_Inst( $refs );
		$inst->deactivation();
		
		flush_rewrite_rules();
	}

	/**
	 *	Disabling Front-End
	 */
	public function redirect_to_backend() 
	{
	    if( !is_admin() ) {
	        wp_redirect( site_url('wp-admin') );
	        exit();
	    }
	}
	
	/**
	 *	defining
	 */
	private function define( $name, $value )
	{
        if ( ! defined( $name ) ) 
        {
            define( $name, $value );
        }
    }
	
	/**
	 *	plugin_url
	 *	@return string	url
	 */
	public function plugin_url()
	{
        return untrailingslashit( plugins_url( "/", __FILE__ ) );
    }
	
	/**
	 *	@return array	$plugin_ref
	 */
	public function get_plugin_ref()
	{
		return $this->plugin_ref;
	}
	
}

function WCWH_Initialize()
{
    return WCWH::instance();
}

// Global for backwards compatibility.
global $wcwh;

$wcwh = WCWH_Initialize(); 

}