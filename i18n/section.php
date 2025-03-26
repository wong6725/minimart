<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	"wh_todo" => array(
		"table" => "todo",
		"table_key" => "id",
		"desc" => "TODO",
	),
	"wh_company" => array(
		"table" => "company",
		"table_key" => "id",
		"desc" => "Company",
	),
	"wh_warehouse" => array(
		"table" => "warehouse",
		"table_key" => "id",
		"desc" => "Warehouse",
	),
	"wh_brand" => array(
		"table" => "brand",
		"table_key" => "id",
		"desc" => "Brand",
	),
	"wh_supplier" => array(
		"table" => "supplier",
		"table_key" => "id",
		"desc" => "Supplier",
	),
	"wh_client" => array(
		"table" => "client",
		"table_key" => "id",
		"desc" => "Client",
	),
	"wh_vending_machine" => array(
		"table" => "vending_machine",
		"table_key" => "id",
		"desc" => "Vending Machine",
	),
	"wh_asset" => array(
		"table" => "asset",
		"table_key" => "id",
		"desc" => "Asset",
	),
	"wh_items" => array(
		"table" => "items",
		"table_key" => "id",
		"desc" => "Items",
	),
	"wh_items_group" => array(
		"table" => "item_group",
		"table_key" => "id",
		"desc" => "Item Group",
	),
	"wh_items_store_type" => array(
		"table" => "item_store_type",
		"table_key" => "id",
		"desc" => "Item Store Type",
	),
	"wh_items_category" => array(
		"table" => "terms",
		"table_key" => "term_id",
		"desc" => "Item Category",
	),
	"wh_items_order_type" => array(
		"table" => "item_reorder_type",
		"table_key" => "id",
		"desc" => "Item Order Type",
	),
	"wh_itemize" => array(
		"table" => "itemize",
		"table_key" => "id",
		"desc" => "Itemize",
	),
	"wh_uom" => array(
		"table" => "uom_conversion",
		"table_key" => "id",
		"desc" => "UOM Conversion",
	),
	"wh_uom_conversion" => array(
		"table" => "uom",
		"table_key" => "term_id",
		"desc" => "Unit of Measure",
	),
	"wh_pricing" => array(
		"table" => "pricing",
		"table_key" => "id",
		"desc" => "Pricing",
	),
	"wh_margin" => array(
		"table" => "pricing",
		"table_key" => "id",
		"desc" => "Margin",
	),
	"wh_promo" => array(
		"table" => "promo_header",
		"table_key" => "id",
		"desc" => "Promotion",
	),
	"wh_customer" => array(
		"table" => "customer",
		"table_key" => "id",
		"desc" => "Customer",
	),
	"wh_customer_group" => array(
		"table" => "customer_group",
		"table_key" => "id",
		"desc" => "Customer Group",
	),
	"wh_origin_group" => array(
		"table" => "customer_origin",
		"table_key" => "id",
		"desc" => "Customer Group",
	),
	"wh_credit" => array(
		"table" => "credit_limit",
		"table_key" => "id",
		"desc" => "Credit Limit",
	),
	"wh_credit_term" => array(
		"table" => "credit_term",
		"table_key" => "id",
		"desc" => "Credit Term",
	),
	"wh_credit_topup" => array(
		"table" => "credit_topup",
		"table_key" => "id",
		"desc" => "Credit TopUp",
	),
	"wh_payment_term" => array(
		"table" => "payment_term",
		"table_key" => "id",
		"desc" => "Payment Term",
	),
	"wh_payment_method" => array(
		"table" => "payment_method",
		"table_key" => "id",
		"desc" => "Payment Method",
	),
	"wh_storage" => array(
		"table" => "storage",
		"table_key" => "id",
		"desc" => "Storage Locations",
	),
	//Docs
	"wh_purchase_request" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Purchase Request",
	),
	"wh_purchase_order" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Purchase Order",
	),
	"wh_good_receive" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Goods Receipt",
	),
	"wh_good_return" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Goods Return",
	),
	"wh_reprocess" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Reprocess",
	),
	"wh_sales_order" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Sales Order",
	),
	"wh_sales_return" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Sales Return",
	),
	"wh_transfer_order" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Transfer Order",
	),
	"wh_good_issue" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Goods Issue",
	),
	"wh_issue_return" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Issue Return",
	),
	"wh_delivery_order" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Delivery Order",
	),
	"wh_do_revise" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "DO Revise",
	),
	"wh_block_stock" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Block Stock",
	),
	"wh_block_action" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Block Stock Action",
	),
	"wh_transfer_item" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Transfer Item",
	),
	"wh_stock_adjust" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Stock Adjustment",
	),
	"wh_stocktake" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "StockTake",
	),
	"wh_pos_transact" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "POS Transaction",
	),
	"wh_storing_list" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Storing List",
	),
	"wh_picking_list" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Picking List",
	),
	"wh_acc_period" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Account Period",
	),
	//remittence money service
	"wh_exchange_rate" => array(
		"table" => "exchange_rate",
		"table_key" => "id",
		"desc" => "Exchange Rate",
	),
	"wh_service_charge" => array(
		"table" => "service_charge",
		"table_key" => "id",
		"desc" => "Service Charge",
	),
	"wh_bankin_service" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Bank In Service",
	),
	"wh_purchase_cdnote" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Purchase Order Credit/Debit Note",
	),
	"wh_sale_cdnote" => array(
		"table" => "document",
		"table_key" => "doc_id",
		"desc" => "Sale Order Credit/Debit Note",
	),
);