<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];
$def_country = ( $args['def_country'] )? $args['def_country'] : 'MY';

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="header-container">
        <h5>Header</h5>
        
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[docno]', 
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['docno'], ( $args['action'] == 'save' )? 1 : $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );
                else $doc_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );
                if( !empty( $datas['ref_doc_date'] ) )
                {
                    $min_date = date( 'm/d/Y', strtotime( $datas['ref_doc_date'] ) );
                    $min_date = 'data-dd-min-date="'.$min_date.'"';

                    $max_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );
                    $max_date = 'data-dd-max-date="'.$max_date.'"';
                }
                else
                {
                    $min_date = ''; $max_date = '';
                } 

                $pview = $view;
                if( $args['action'] == 'update-header' ) $pview = true;

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"', $min_date, $max_date ], 
                        'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $pview 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $view && empty( $datas['posting_date'] ) ) $datas['posting_date'] = $datas['post_date'];
                if( $datas['posting_date'] ) $posting_date = date( 'm/d/Y', strtotime( $datas['posting_date'] ) );

                wcwh_form_field( $prefixName.'[posting_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Posting Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$posting_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['posting_date'] )? date( 'Y-m-d', strtotime( $datas['posting_date'] ) ) : "", $pview 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['ref_doc_id'] )
                {
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'Purchase Request No.', 'required'=>false, 'attrs'=>[] ], 
                        $datas['purchase_doc'], true 
                    );  
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                        $datas['purchase_doc'], $view
                    );  
                }
                else
                {
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'label'=>'Purchase Request No.', 'required'=>false, 'attrs'=>[],'class'=>[] ], 
                        $datas['purchase_doc'], $view 
                    );  
                }
                wcwh_form_field( $prefixName.'[ref_doc_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['ref_doc_id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                
                if( $datas['ref_doc_id'] && $datas['client_company_code'] )
                {
                    wcwh_form_field( $prefixName.'[client_company_code]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'Client', 'required'=>false, 'attrs'=>[] ], 
                        $options[ $datas['client_company_code'] ], true 
                    );  
                    wcwh_form_field( $prefixName.'[client_company_code]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                        $datas['client_company_code'], $view
                    );  
                }
                else
                {
                    $cc = $args['setting'][ $args['section'] ]['default_client'];
                    wcwh_form_field( $prefixName.'[client_company_code]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Client', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                            'options'=> $options
                        ], 
                        ( $datas['client_company_code'] )? $datas['client_company_code'] : $cc, $view 
                    ); 
                }
            ?>
            </div>
        </div>

        <div class="form-row">    
            <div class="col form-group">
            <?php
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_payment_term', $filter, [], false, [] ), 'code', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[payment_term]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Payment Term', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['payment_term'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                wcwh_form_field( $prefixName.'[sap_po]', 
                    [ 'id'=>'', 'label'=>'SAP PO No.', 'required'=>false, 'attrs'=>[],'class'=>[] ], 
                    $datas['sap_po'], $view 
                );  
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[remark]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remark'], $view 
                ); 

                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['warehouse_id'], $view 
                ); 
            ?>
            </div>
        </div>
    
    </div>

    <?php if( current_user_cans( ['discount_wh_sales_order'] ) ): ?>
    <div class="header-container">
        <h5>Discount</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $pview = $view;
                if( $args['action'] == 'update-header' ) $pview = true;
                wcwh_form_field( $prefixName.'[discount]', 
                    [ 'id'=>'discount', 'label'=>'Header / Total Discount (Eg. 10% or 10)', 'required'=>false, 'attrs'=>[],
                        'description'=>'Discount by Percentage (10%) or by Amount (10)' ], 
                    $datas['discount'], $pview 
                ); 
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="header-container">
        <div class="form-row">
            <div class="col form-group">
                <h5>Change of Billing</h5>
            </div>
            <div class="col form-group">
                <h5>Change of Delivery</h5>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_billing_contact]', 
                    [ 'id'=>'diff_billing_address', 'type'=>'text', 'label'=>'Billing Contact Info', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional (full contact person with phone no.), empty for default client contact' ], 
                    $datas['diff_billing_contact'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_shipping_contact]', 
                    [ 'id'=>'diff_shipping_address', 'type'=>'text', 'label'=>'Delivery Contact Info', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional (full contact person with phone no.), empty for default client contact' ], 
                    $datas['diff_shipping_contact'], $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_billing_address]', 
                    [ 'id'=>'diff_billing_address', 'type'=>'textarea', 'label'=>'Billing Address', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional, empty for default client address' ], 
                    $datas['diff_billing_address'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_shipping_address]', 
                    [ 'id'=>'diff_shipping_address', 'type'=>'textarea', 'label'=>'Delivery Address', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional, empty for default client address' ], 
                    $datas['diff_shipping_address'], $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $country = !empty( $datas['diff_billing_country'] )? $datas['diff_billing_country'] : $def_country;
                $countries = WCWH_Function::get_countries();
                $options = options_data( $countries );

                wcwh_form_field( $prefixName.'[diff_billing_country]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Billing Country', 'required'=>false, 
                        'attrs'=>[ 'data-state_target=".diff_billing_state"' ], 
                        'class'=>['select2Strict', 'dynamicCountryState'],
                        'options'=> $options, 'description'=>'Optional, empty for default client country'
                    ], 
                    $country, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $state = !empty( $datas['diff_billing_state'] )? $datas['diff_billing_state'] : '';
                $states = WCWH_Function::get_states( $country );
                if( empty( $states ) && !empty( $datas['diff_billing_state'] ) ) $states[ $datas['diff_billing_state'] ] = $datas['diff_billing_state'];
                $options = options_data( $states );

                wcwh_form_field( $prefixName.'[diff_billing_state]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Billing State', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'diff_billing_state'],
                        'options'=> $options, 'description'=>'Optional, empty for default client address'
                    ], 
                    $state, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $country = !empty( $datas['diff_shipping_country'] )? $datas['diff_shipping_country'] : $def_country;
                $countries = WCWH_Function::get_countries();
                $options = options_data( $countries );

                wcwh_form_field( $prefixName.'[diff_shipping_country]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Shipping Country', 'required'=>false, 
                        'attrs'=>[ 'data-state_target=".diff_shipping_state"' ], 
                        'class'=>['select2Strict', 'dynamicCountryState'],
                        'options'=> $options, 'description'=>'Optional, empty for default client country'
                    ], 
                    $country, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $state = !empty( $datas['diff_shipping_state'] )? $datas['diff_shipping_state'] : '';
                $states = WCWH_Function::get_states( $country );
                if( empty( $states ) && !empty( $datas['diff_billing_state'] ) ) $states[ $datas['diff_billing_state'] ] = $datas['diff_billing_state'];
                $options = options_data( $states );

                wcwh_form_field( $prefixName.'[diff_shipping_state]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Shipping State', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'diff_shipping_state'],
                        'options'=> $options, 'description'=>'Optional, empty for default client address'
                    ], 
                    $state, $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_billing_city]', 
                    [ 'id'=>'', 'label'=>'Billing City', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional, empty for default client address' ], 
                    $datas['diff_billing_city'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_billing_postcode]', 
                    [ 'id'=>'postcode', 'label'=>'Billing Postcode', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional, empty for default client address' ], 
                    $datas['diff_billing_postcode'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_shipping_city]', 
                    [ 'id'=>'', 'label'=>'Shipping City', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional, empty for default client address' ], 
                    $datas['diff_shipping_city'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[diff_shipping_postcode]', 
                    [ 'id'=>'postcode', 'label'=>'Shipping Postcode', 'required'=>false, 'attrs'=>[],
                        'description'=>'Optional, empty for default client address' ], 
                    $datas['diff_shipping_postcode'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <div class="detail-container">
        <h5>Details</h5>
        <?php if( $args['action'] == 'update-header' ) $view = true; ?>
        <?php if( ! $view ): ?>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                
                ///-------------------------12/9/22---------------------
                $filter = [ 'wh_code'=>$datas['warehouse_id'], 'sys_reserved'=>'staging' ];
                $storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

                $filter = [ 'status'=>'all' ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                //$items = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'usage'=>1, 'uom'=>1, 'isUnit'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                $items = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'usage'=>1, 'uom'=>1, 'isUnit'=>1, 'inventory'=>$storage['id'], 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'], 'meta'=>['returnable_item'] ] );
                ///-------------------------12/9/22---------------------

                /*$filter = [ 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $items = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'uom'=>1, 'isUnit'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );*/

                if( $items )
                {
                    ///-------------------------12/9/22---------------------
                    $c_items = []; $inventory = [];
                    foreach( $items as $i => $item ) if( $item['parent'] > 0 ) $c_items[] = $item['id'];
                    if( count( $c_items ) > 0 )
                    {
                        $filter = [ 'wh_code'=>$datas['warehouse_id'], 'sys_reserved'=>'staging' ];
                        $storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

                        $filter = [ 'wh_code'=>$datas['warehouse_id'], 'strg_id'=>$storage['id'], 'item_id'=>$c_items ];
                        $stocks = apply_filters( 'wcwh_get_stocks', $filter, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                        if( $stocks )
                        foreach( $stocks as $i => $stock )
                        {
                            $inventory[ $stock['id'] ] = $stock;
                        }
                    }
                    ///-------------------------12/9/22---------------------

                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $items as $i => $item )
                    {
                        ///-------------------------12/9/22---------------------
                        $stk = ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['qty'] : $item['stock_qty'];
                        $stk-= ( $inventory[ $item['id'] ] )? $inventory[ $item['id'] ]['allocated_qty'] : $item['stock_allocated'];
                        ///-------------------------12/9/22---------------------   
                        $fraction = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';
                        $inconsistent = ( $item['required_unit'] )? '' : 'readonly';
                        $returnable_cb = ( $item['returnable_item'] )? '' : 'd-none';
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-bqty="" 
                            data-sunit="" 
                            data-item_id="" 
                            data-uom="'.$item['uom_code'].'" 
                            data-fraction="'.$fraction.'" 
                            data-inconsistent="'.$inconsistent.'"  
                            data-sku="'.$item['_sku'].'" 
                            data-code="'.$item['code'].'"
                            data-serial="'.$item['serial'].'" 
                            data-item="'.$item['code'].' - '.$item['name'].'" 
                            data-ref_bqty="0" 
                            data-ref_bal="0" 
                            data-ref_doc_id="" 
                            data-ref_item_id=""
                            data-dremark=""
                            data-foc=""
                            data-discount=""
                            data-def_price="'.$item['unit_price'].'"
                            data-cprice="'.$item['unit_price'].'"
                            data-custom_item=""
                            data-item_number=""
                            data-stocks="'.round_to( $stk, 2 ).'"
                            data-returnable_item ="'.$item['returnable_item'].'" 
                            data-returnable_cb ="'.$returnable_cb.'" 
                        >'. $item['code'].', '.$item['uom_code'].', '.$item['name'] .'</option>';
                    }
                    echo '</select>';
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".pr-items" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <div class="actions row">
            <div class="col-md-3">
                <?php
                    if( $args['setting'][ $args['section'] ]['custom_product'] ):
                    echo '<a class="btn btn-sm btn-primary dynamic-element" data-source="" data-tpl="'.$args['rowCusTpl'].'TPL" 
                        data-target="#item_row"
                            data-id="" 
                            data-product_id="" 
                            data-bqty="" 
                            data-sunit="" 
                            data-item_id="" 
                            data-uom="" 
                            data-fraction="positive-integer" 
                            data-inconsistent="readonly"  
                            data-sku="" 
                            data-code=""
                            data-serial="" 
                            data-item="" 
                            data-ref_bqty="0" 
                            data-ref_bal="0" 
                            data-ref_doc_id="" 
                            data-ref_item_id=""
                            data-dremark=""
                            data-foc=""
                            data-discount=""
                            data-def_price=""
                            data-cprice=""
                            data-custom_item=""
                            data-uom_id=""
                            data-item_number=""
                            data-stocks=""
                        >Add Custom Item +</a>';
                    endif;
                ?>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <th class="qty">Def Price<sup class="toolTip" title="Default price for reference, Price might differ on order placement, affected by chosen client"> ? </sup></th>
                    <th class="stocks">Stocks</th>
                    <th class="ref_qty" >Ref Qty <sup class="toolTip" title="Qty of reference document"> ? </sup></th>
                    <th class="balance">Bal <sup class="toolTip" title="Balance of reference document"> ? </sup></th>
                    <th class="qty">Qty</th>
                    <!-- <th class="unit">Metric (kg/l)</th> -->
                <?php if( $args['setting'][ $args['section'] ]['custom_price'] ): ?>
                    <th class="qty">Unit Price</th>
                <?php endif; ?>
                <?php if( current_user_cans( ['discount_wh_sales_order'] ) ): ?>
                    <th class="qty">Foc</th>
                    <th class="qty">Discount<sup title="" data-toggle="tooltip" data-placement="right" data-original-title="Discount by Percentage (10%) or by Amount (10)">&nbsp;?&nbsp;</sup></th>
                <?php endif; ?>
                <?php if( current_user_cans( ['returnable_wh_sales_order'] ) ): ?>
                    <th class="num"><sup class="toolTip" title="Returnable Item (Direct Good Receipt on Delivered)"> ? </sup></th>
                <?php endif; ?>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="item_row" class="sortable_row">
            <?php
                if( $datas['details'] )
                {
                    foreach( $datas['details'] as $i => $row )
                    {
                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'product_id' => '{product_id}', 
                            'item_id' => '{item_id}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'bqty' => '{bqty}', 
                            'sunit' => '{sunit}', 
                            'ref_bqty' => '{ref_bqty}',
                            'ref_bal' => '{ref_bal}',
                            'ref_doc_id' => '{ref_doc_id}',
                            'ref_item_id' => '{ref_item_id}',
                            'fraction' => '{fraction}',
                            'inconsistent' => '{inconsistent}',
                            'readonly' => '{readonly}',
                            'hidden' => '{hidden}',
                            'dremark' => '{dremark}',
                            'foc' => '{foc}',
                            'discount' => '{discount}',
                            'def_price' => '{def_price}',
                            'cprice' => '{cprice}',
                            'custom_item' => '{custom_item}',
                            'uom_id' => '{uom_id}',
                            'item_number' => '{item_number}',
                            'stocks' => '{stocks}',
                            'stocks_clr' => '{stocks_clr}',
                            'returnable_item' => '{returnable_item}',
                            'returnable_cb' => '{returnable_cb}',
                            'receive_on_deliver' => '{receive_on_deliver}',
                        ];

                        $filter = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $filter = [ 'wh_code'=>$datas['warehouse_id'], 'sys_reserved'=>'staging' ];
                        $storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_latest_price', $filter, [], true, [ 'usage'=>1, 'uom'=>1, 'category'=>1, 'isUnit'=>1, 'inventory'=>$storage['id'] ] );

                        if( ! $row['line_item']['unit_price'] )
                        {
                            $price = apply_filters( 'wcwh_get_price', $row['product_id'], $args['seller'] );
                            if( $price ) $row['line_item']['unit_price'] = $price['unit_price'];
                        }

                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['item_id'],
                            'item' => $row['line_item']['code'].' - '.$row['line_item']['name'],
                            'uom' => $row['line_item']['uom_code'],
                            'bqty' => round_to( $row['bqty'], 2 ), 
                            'sunit' => round_to( $row['sunit'], 3 ), 
                            'ref_bqty' => round_to( $row['ref_bqty'], 2 ),
                            'ref_bal' => round_to( $row['ref_bal'], 2 ),
                            'ref_doc_id' => $row['ref_doc_id'],
                            'ref_item_id' => $row['ref_item_id'],
                            'fraction' => ( $row['line_item']['uom_fraction'] )? 'positive-number' : 'positive-integer',
                            'inconsistent' => ( $row['line_item']['required_unit'] )? '' : 'readonly',
                            'readonly' => ( $row['lqty'] <= 0 )? '' : '',//readonly
                            'hidden' => ( $row['lqty'] <= 0 )? '' : '',//display-none
                            'dremark' => $row['dremark'],
                            'foc' => $row['foc'],
                            'discount' => $row['discount'],
                            'def_price' => $row['line_item']['unit_price'],
                            'cprice' => $row['cprice'],
                            'custom_item' => $row['custom_item'],
                            'uom_id' => $row['uom_id'],
                            'item_number' => $row['_item_number'],
                            'stocks' => round_to( $row['line_item']['stocks'], 2 ),
                            'stocks_clr' => ( $row['line_item']['stocks'] < $row['ref_bal'] || $row['line_item']['stocks'] < $row['bqty'] )? 'clr-red' : '',
                            'returnable_item' => ( $row['returnable_item'] > 0 )? $row['returnable_item']: '',
                            'returnable_cb' => ( $row['returnable_item'] > 0 )? '': 'd-none',
                            'receive_on_deliver' => ( $row['receive_on_deliver'] )? 'checked' : '',
                        ];
                        $ag = $row;
                        $ag['setting'] = $args['setting'];
                        $ag['section'] = $args['section'];

                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/saleOrder-row.php', $ag );
                        if( ! empty( $row['custom_item'] ) )
                            $tpl = apply_filters( 'wcwh_get_template_content', 'segment/saleOrder-customRow.php', $ag );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }
                }
            ?>
            </tbody>
        </table>
        <?php else: ?>
             <div class="form-row">
                <div class="col form-group">
                <?php 
                    echo $args['render'];
                ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php if( $args['setting'][ $args['section'] ]['fees'] ): ?>
    <div class="detail-container">
        <?php if( ! $view ): ?>
        <h5>Other Fees</h5>
        <div class="actions row">
            <div class="col-md-2">
                <a class="btn btn-sm btn-primary dynamic-element" data-source="" data-tpl="<?php echo $args['feeTpl'] ?>TPL" 
                    data-target="#fee_row" data-fee_name="" data-fee=""
                >+ Custom Fee</a>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Fee / Tax Name</th>
                    <th class="item">Amount</th>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="fee_row">
            <?php
                if( $datas['fees'] )
                {
                    foreach( $datas['fees'] as $i => $row )
                    {
                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'fee_name' => '{fee_name}',
                            'fee' => '{fee}',
                        ];

                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['id'], 
                            'fee_name' => $row['fee_name'],
                            'fee' => $row['fee'], 
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/saleOrder-FeeRow.php' );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }
                }
            ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <?php if( $datas['doc_id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>