<?php 
	$prefixName = "wcwh_option";

	$datas = $args['datas'];
	//pd($datas);
?>
<form id="actionSetting" class="" action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate >
	<div class='form-rows-group'>
    	<h5>Common Setting</h5>
    	<div class="form-row">
	        <div class="col form-group">
	        	<?php 
	                wcwh_form_field( $prefixName.'[begin_date]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'System Begin Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
	                    $datas['begin_date'], $view 
	                ); 
	            ?>
	        </div>
	        <div class="col form-group">
	        	<?php 
	                wcwh_form_field( $prefixName.'[inv_db_suffix]', 
	                    [ 'id'=>'', 'type'=>'text', 'label'=>'Previous Inventory DB Suffix', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
	                    $datas['inv_db_suffix'], $view 
	                ); 
	            ?>
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group">
	        	<h6>Need of Module or Master:</h6>
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_asset]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Asset', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_asset'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_vending_machine]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Vending Machine', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_vending_machine'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_brand]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Brand', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_brand'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_item_storing_type]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Item Storing Type', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_item_storing_type'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_uom_conversion]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need UOM Conversion', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_uom_conversion'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_reprocess_item]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Reprocess Item', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_reprocess_item'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_itemize]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Itemize', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_itemize'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_price_margin]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Price Margin', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_price_margin'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_promo]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Promotion', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_promo'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_customer]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Customer', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_customer'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_credit]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Credit Limit', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_credit'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_payment_method]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Payment Method', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_payment_method'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_payment_term]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Payment Term', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_payment_term'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_margining]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Margining Control', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_margining'], $view 
		            ); 
		        ?>
	        	</div><br/>
	        </div>

	        <div class="col form-group">
	        	<h6>Need of Report:</h6>
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_customer_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Customer Reports', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_customer_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_credit_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Credit Reports', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_credit_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_tool_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Tool Credit Reports', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_tool_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_receipt_count]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Receipts Count', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_receipt_count'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_momawater_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'MOMAwater Report', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_momawater_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_itemize_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Itemize Report', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_itemize_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_foodboard_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'FoodBoard Report', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_foodboard_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_estate_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Estate Office Report', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_estate_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_estate_expenses_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Estate Expenses Report', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_estate_expenses_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_report][wh_et_price_rpt]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Foodboard / Estate Pricing', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_report']['wh_et_price_rpt'], $view 
		            ); 
		        ?>
	        	</div><br/>
	        	
	        </div>

	        <div class="col form-group">
	        	<h6>Need of Chart:</h6>
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_chart][access_foodboard_wh_charts]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'FoodBoard Chart', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_chart']['access_foodboard_wh_charts'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[general][use_chart][wh_estate_chart]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Estate Office Chart', 'required'=>false, 'attrs'=>[] ], 
		                $datas['general']['use_chart']['wh_estate_chart'], $view 
		            ); 
		        ?>
	        	</div><br/>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
		<h5>Sync Integration:</h5>
	    <div class="form-row">
	        <div class="col form-group">
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[wh_sync][use_sync]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Sync', 'required'=>false, 'attrs'=>[] ], 
		                $datas['wh_sync']['use_sync'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[wh_sync][receive_sync]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Receive Sync', 'required'=>false, 'attrs'=>[] ], 
		                $datas['wh_sync']['receive_sync'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div class="form-row">
	        		<div class="col form-group">
			        <?php 
			            wcwh_form_field( $prefixName.'[wh_sync][data_per_connect]', 
			                [ 'id'=>'', 'type'=>'number', 'label'=>'Data Per Connection', 'required'=>false, 'attrs'=>[] ], 
			                $datas['wh_sync']['data_per_connect'], $view 
			            ); 
			        ?>
	        		</div>
	        		<div class="col form-group">
			        <?php 
			            wcwh_form_field( $prefixName.'[wh_sync][connection_timeout]', 
			                [ 'id'=>'', 'type'=>'number', 'label'=>'Connection Timeout(sec)', 'required'=>false, 'attrs'=>[] ], 
			                $datas['wh_sync']['connection_timeout'], $view 
			            ); 
			        ?>
	        		</div>
	        	</div>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
		<h5>SAP Middleware Integration:</h5>
	    <div class="form-row">
	        <div class="col form-group">
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[middleware][use_integrate]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need Integrate', 'required'=>false, 'attrs'=>[] ], 
		                $datas['middleware']['use_integrate'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<div class="form-row">
	        		<div class="col form-group">
			        <?php 
			            wcwh_form_field( $prefixName.'[middleware][key]', 
			                [ 'id'=>'', 'type'=>'text', 'label'=>'Integration Private Key', 'required'=>false, 'attrs'=>[] ], 
			                $datas['middleware']['key'], $view 
			            ); 
			            //$@p{year}&{hour}%devtest#
			        ?>
	        		</div>
	        		<div class="col form-group">
			        <?php 
			            wcwh_form_field( $prefixName.'[middleware][period]', 
			                [ 'id'=>'', 'type'=>'number', 'label'=>'Integration Day in Week', 'required'=>false, 'attrs'=>[] ], 
			                $datas['middleware']['period'], $view 
			            ); 
			            //http://localhost:8899/saprfc/api/hr/minimart/
			        ?>
	        		</div>
	        	</div>
	        	<div class="form-row">
	        		<div class="col form-group">
			        <?php 
			            wcwh_form_field( $prefixName.'[middleware][url]', 
			                [ 'id'=>'', 'type'=>'text', 'label'=>'Integration Url', 'required'=>false, 'attrs'=>[] ], 
			                $datas['middleware']['url'], $view 
			            ); 
			            //http://localhost:8899/saprfc/api/hr/minimart/
			        ?>
	        		</div>
	        	</div>
	        </div>
	    </div>
	</div>
	
	<div class='form-rows-group'>
		<h5>MyInvoice Integration:</h5>
	    <div class="form-row">
	        <div class="col form-group">
	        	<div class="form-row">
	        		<div class="col form-group">
			        <?php 
			            wcwh_form_field( $prefixName.'[myinvoice][recipient]', 
			                [ 'id'=>'', 'type'=>'textarea', 'label'=>'Handshake Email Recipient', 'required'=>false, 'attrs'=>[] ], 
			                $datas['myinvoice']['recipient'], $view 
			            ); 
			        ?>
	        		</div>
	        	</div>
	        </div>
	    </div>
	</div>
	
	<div class='form-rows-group'>
    	<h5>POS Setting</h5>
	    <div class="form-row">
	        <div class="col form-group">
	        	<h6>Need of POS Function:</h6>
	        	<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[pos][price_log]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Log Price On Each Transaction', 'required'=>false, 'attrs'=>[] ], 
		                $datas['pos']['price_log'], $view 
		            ); 
		        ?>
	        	</div><br/>

	        	<!--<div>
		        <?php 
		            /*wcwh_form_field( $prefixName.'[pos][pos_transaction]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Need POS Transaction', 'required'=>false, 'attrs'=>[] ], 
		                $datas['pos']['pos_transaction'], $view 
		            );*/ 
		        ?>
	        	</div><br/>-->
	        </div>
	        <div class="col form-group">
	    		<div>
	    		<?php 
		            wcwh_form_field( $prefixName.'[pos][pos_auto_do]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Pos Auto Delivery Order Creation', 'required'=>false, 'attrs'=>[] ], 
		                $datas['pos']['pos_auto_do'], $view 
		            ); 
		        ?>
		        </div>	    		
	    	</div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Pricing</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [], false, [] ), 'code', [ 'code','name' ], '' );

	            wcwh_form_field( $prefixName.'[wh_pricing][combine_seller][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'To Combine', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, ], 
	                $datas['wh_pricing']['combine_seller'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_pricing][combine_name]', 
	                [ 'id'=>'', 'type'=>'text', 'label'=>'Combine Seller Name', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
	                $datas['wh_pricing']['combine_name'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Customer</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_customer_group', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_customer][default_credit_group]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Default Credit Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'Default Credit Group for newly created customer', ], 
	                $datas['wh_customer']['default_credit_group'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_account_type', [], [], false, [] ), 'id', [ 'code', 'employee_prefix' ], '' );

	            wcwh_form_field( $prefixName.'[wh_customer][non_editable_by_acc_type][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Exclude Customer Editing by Account Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 'multiple'=>1, 
                        'options'=> $options, 'description'=>'Exclude customer with specific account type from editing', ], 
	                $datas['wh_customer']['non_editable_by_acc_type'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_customer_group', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_customer][rms_credit_group]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'RMS Default Credit Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'Default Credit Group for customer who wish to use RMS only', ], 
	                $datas['wh_customer']['rms_credit_group'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>MSPO Hide</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'mspo'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ] );

	            wcwh_form_field( $prefixName.'[mspo_hide][items][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Item To Hide', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Item to be hidden' ], 
	                $datas['mspo_hide']['items'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>

<div class="accordion">
	<div class="accordion-row">
		<a class="accordion-trigger leftered fullWidth btn btn-primary btn-sm" data-toggle="collapse" href="#Outlet" aria-expanded="true" aria-controls="Outlet">
			<h4>Outlet Setting </h4>
		</a>
    	<div id="Outlet" class="collapse show">
<?php #---------------------------------------------------------------------------------- ?>
	<div class='form-rows-group'>
    	<h5>Inventory</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_inventory][use_reserved]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Show Reserved Qty', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_inventory']['use_reserved'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_inventory][use_allocate]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Show POS / Allocate Qty', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_inventory']['use_allocate'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_inventory][strict_doc_date_deduction]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Inventory Deduction by Accurate Date', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_inventory']['strict_doc_date_deduction'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>POS Delivery Order</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_pos_do][add_item]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'POS Delivery Order Allow Add Item', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_pos_do']['add_item'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_pos_do][del_item]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'POS Delivery Order Allow Delete Item', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_pos_do']['del_item'], $view 
	            ); 
	        ?>
	        </div>
	    </div>

	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_pos_do][locked_bqty]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'POS Delivery Lock Item Bqty', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_pos_do']['locked_bqty'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Purchase Request</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_purchase_request][strict_unpost]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Use Strict Unpost', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Unpost To check over integrated system' ], 
	                $datas['wh_purchase_request']['strict_unpost'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_purchase_request][no_kg]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'No Metric(KG/L) Item allowed', 'required'=>false, 'attrs'=>[],
	                	'description'=>'' ], 
	                $datas['wh_purchase_request']['no_kg'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_item_category', [], [], false, [] ), 'id', [ 'slug', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_purchase_request][no_kg_excl_cat][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'No Metric Category Exclusive', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Categories excluded from No Metric control' ], 
	                $datas['wh_purchase_request']['no_kg_excl_cat'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Goods Receipt</h5>
    	<div class="form-row">
    		<div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_good_receive][no_kg]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'No Metric(KG/L) Item allowed', 'required'=>false, 'attrs'=>[],
	                	'description'=>'' ], 
	                $datas['wh_good_receive']['no_kg'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_item_category', [], [], false, [] ), 'id', [ 'slug', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_good_receive][no_kg_excl_cat][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'No Metric Category Exclusive', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Categories excluded from No Metric control' ], 
	                $datas['wh_good_receive']['no_kg_excl_cat'], $view 
	            ); 
	        ?>
	        </div>
    	</div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_good_receive][use_ref_only]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Goods Receipt By Ref Doc Only', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_good_receive']['use_ref_only'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_good_receive][use_expiry]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Goods Receipt With Expiration Date', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_good_receive']['use_expiry'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	    	<div class="col form-group flex-row flex-align-center">
	        <?php 
	        	wcwh_form_field( $prefixName.'[wh_good_receive][use_direct_issue]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Goods Receipt Direct Issue', 'required'=>false, 'attrs'=>[],
	                	'description'=>'On Goods Receipt Direct Issue For Consumption (use on branch / outlet)', ], 
	                $datas['wh_good_receive']['use_direct_issue'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_supplier', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_good_receive][default_supplier]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Default Supplier', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'Goods Receipt Form Default Supplier Selection', ], 
	                $datas['wh_good_receive']['default_supplier'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_good_receive][use_auto_sales]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'GR Auto Sales & Delivery', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Goods Receipts Create Sale Order and Delivery Order Directly' ], 
	                $datas['wh_good_receive']['use_auto_sales'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );

	            wcwh_form_field( $prefixName.'[wh_good_receive][auto_sales_client][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Auto Sales Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'description'=>'Default Client On Auto Sales Case', 'multiple'=>1 ], 
	                $datas['wh_good_receive']['auto_sales_client'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Sales Order</h5>
    	<div class="form-row">
	    	<div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_sales_order][custom_price]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Place Order with Custom Price', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_sales_order']['custom_price'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_sales_order][custom_product]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Allow Custom Product', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_sales_order']['custom_product'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	    	<div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_sales_order][dremark]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Allow Item Remark', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_sales_order']['dremark'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_sales_order][fees]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Allow Custom Fees', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_sales_order']['fees'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_sales_order][default_client]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Default Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'Sale Order Form Default Client Selection', ], 
	                $datas['wh_sales_order']['default_client'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
	            wcwh_form_field( $prefixName.'[wh_sales_order][direct_issue_client][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Direct Issue Client', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
	                    'options'=> $options, 'multiple'=>1
	                ], 
	                $datas['wh_sales_order']['direct_issue_client'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <div class="form-row">
	    	<div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_item_category', [], [], false, [] ), 'id', [ 'slug', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_sales_order][no_kg_excl_cat][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'No Metric Category Exclusive', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Categories excluded from No Metric control' ], 
	                $datas['wh_sales_order']['no_kg_excl_cat'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Delivery Order</h5>
	    <div class="form-row">
	    	<div class="col form-group flex-row flex-align-center">
    		<?php 
	            wcwh_form_field( $prefixName.'[wh_delivery_order][use_auto_sales]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Manual DO Auto Sales', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Delivery Order Create Sale Order automatically After Posting' ], 
	                $datas['wh_delivery_order']['use_auto_sales'], $view 
	            ); 
	        ?>
	    	</div>
	    	<div class="col form-group flex-row flex-align-center">
	    	<?php
	    		wcwh_form_field( $prefixName.'[wh_delivery_order][auto_sales_limit]', 
	                [ 'id'=>'', 'type'=>'text', 'label'=>'Auto SO limit item', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'],
	                	'description'=>'Manual DO created Sale Order limit item rows', ], 
	                $datas['wh_delivery_order']['auto_sales_limit'], $view 
	            ); 
	    	?>
	    	</div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_delivery_order][strict_unpost]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Use Strict Unpost', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Unpost To check over integrated system' ], 
	                $datas['wh_delivery_order']['strict_unpost'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_delivery_order][add_item]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Delivery Order Allow Add Item', 'required'=>false, 'attrs'=>[] ], 
	                $datas['wh_delivery_order']['add_item'], $view 
	            ); 
	        ?>
	        </div>
	    </div>

	    <div class="form-row">
	    	<div class="col form-group flex-row flex-align-center">
	        <?php 
	        	wcwh_form_field( $prefixName.'[wh_delivery_order][use_auto_po]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Auto PO on DO Import', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Create Purchase Order on Delivery Order Import', ], 
	                $datas['wh_delivery_order']['use_auto_po'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            $options = options_data( apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [], false, [] ), 'code', [ 'code','name' ], '' );
                
	            wcwh_form_field( $prefixName.'[wh_delivery_order][auto_po_source][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Auto PO Source', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
	                    'options'=> $options, 'multiple'=>1
	                ], 
	                $datas['wh_delivery_order']['auto_po_source'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Goods Issue</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_good_issue][use_direct_consume]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Use Direct Consume', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Goods Issue Allow To Create Direct Consume' ], 
	                $datas['wh_good_issue']['use_direct_consume'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );

	            wcwh_form_field( $prefixName.'[wh_good_receive][direct_issue_client]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Direct Issue Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'Default Client On Direct Issue Case', ], 
	                $datas['wh_good_receive']['direct_issue_client'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Good Return</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_good_return][strict_unpost]', 
	                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Use Strict Unpost', 'required'=>false, 'attrs'=>[],
	                	'description'=>'Unpost To check over integrated system' ], 
	                $datas['wh_good_return']['strict_unpost'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Tool Request</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$filter = [];
                $options = options_data( apply_filters( 'wcwh_get_item_group', $filter, [], false, [] ), 'id', [ 'code', 'name' ], '' );

	            wcwh_form_field( $prefixName.'[wh_tool_request][used_item_group][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Item Group Used', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
	                	'options'=> $options, 'multiple'=>1, 'description'=>'' ], 
	                $datas['wh_tool_request']['used_item_group'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$tool_acc_type = options_data( apply_filters( 'wcwh_get_account_type', [], [], false, [] ), 'code', [ 'code', 'employee_prefix' ], '' );

	            wcwh_form_field( $prefixName.'[wh_tool_request][acc_type_to_limit][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Account Type to Limit Item', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 'multiple'=>1, 
                        'options'=> $tool_acc_type, 'description'=>'Account Type required to limit by Item Group', ], 
	                $datas['wh_tool_request']['acc_type_to_limit'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_tool_request][group_to_limit][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Item Group to Limit', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
	                	'options'=> $options, 'multiple'=>1 ], 
	                $datas['wh_tool_request']['group_to_limit'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_tool_rpt][def_item_group]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Credit Report Item Group', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
	                	'options'=> $options ], 
	                $datas['wh_tool_rpt']['def_item_group'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_tool_rpt][tool_wage]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Tool Wage Item Group', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
	                	'options'=> $options ], 
	                $datas['wh_tool_rpt']['tool_wage'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	            wcwh_form_field( $prefixName.'[wh_tool_rpt][eq_wage]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Equipment Wage Item Group', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
	                	'options'=> $options ], 
	                $datas['wh_tool_rpt']['eq_wage'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Spare Parts Request</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$filter = [];
                $options = options_data( apply_filters( 'wcwh_get_item_group', $filter, [], false, [] ), 'id', [ 'code', 'name' ], '' );

	            wcwh_form_field( $prefixName.'[wh_parts_request][used_item_group][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Item Group Used', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
	                	'options'=> $options, 'multiple'=>1, 'description'=>'' ], 
	                $datas['wh_parts_request']['used_item_group'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>
<?php #---------------------------------------------------------------------------------- ?>
		</div>
	</div>

<div class="accordion-row">
		<a class="accordion-trigger leftered fullWidth btn btn-primary btn-sm" data-toggle="collapse" href="#Report" aria-expanded="true" aria-controls="Report">
			<h4>Report Setting </h4>
		</a>
    	<div id="Report" class="collapse show">
<?php #---------------------------------------------------------------------------------- ?>
	<div class='form-rows-group'>
    	<h5>General Report</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$users = get_simple_users();
	        	$options = options_data( $users, 'ID', [ 'name', 'display_name' ] );

	            wcwh_form_field( $prefixName.'[general_report][confirm_by]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Document Print Confirmed By', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'Printing Report Add Confirm By Personnel' ], 
	                $datas['general_report']['confirm_by'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>

<?php if( $datas['general']['use_report']['wh_foodboard_rpt'] ): ?>
	<div class='form-rows-group'>
    	<h5>Foodboard Report</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_item_category', [], [], false, [] ), 'id', [ 'slug', 'name' ] );

	            wcwh_form_field( $prefixName.'[foodboard_report][categories][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Foodboard Categories', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Categories specifies for foodboard report' ], 
	                $datas['foodboard_report']['categories'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>
<?php endif; ?>

<?php if( $datas['general']['use_report']['wh_et_price_rpt'] ): ?>
	<div class='form-rows-group'>
    	<h5>Foodboard / Estate Pricing Report</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_store_type', [], [], false, [] ), 'id', [ 'code', 'name' ] );

	            wcwh_form_field( $prefixName.'[et_pricing_report][store_type][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Storage Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Storage type specifies for Foodboard / Estate pricing report' ], 
	                $datas['et_pricing_report']['store_type'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>
<?php endif; ?>

<?php if( $datas['general']['use_report']['wh_momawater_rpt'] ): ?>
	<div class='form-rows-group'>
    	<h5>MOMAwater Report</h5>
	    <div class="form-row">
	        <div class="col form-group flex-row flex-align-center">
	        <?php 
	        	$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ] );

	            wcwh_form_field( $prefixName.'[momawater_report][items][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'MOMAwater Product', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1, 'description'=>'Product specifies for MOMAwater report' ], 
	                $datas['momawater_report']['items'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group flex-row flex-align-center">
	        
	        </div>
	    </div>
	</div>
<?php endif; ?>

	<?php #---------------------------------------------------------------------------------- ?>
		</div>
	</div>
</div>

	<?php  
		wcwh_form_field( $prefixName.'[last_update]', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        current_time( 'mysql' ), $view 
	    ); 
	    wcwh_form_field( 'token', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        $args['token'], $view 
	    ); 
	?>

	<?php submit_button(); ?>

</form>
