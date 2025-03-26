<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

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
                if( !empty( $datas['doc_date'] ) ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );
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

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"', $min_date, $max_date ], 
                        'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $view && empty( $datas['posting_date'] ) ) $datas['posting_date'] = $datas['post_date'];
                if( $datas['posting_date'] ) $posting_date = date( 'm/d/Y', strtotime( $datas['posting_date'] ) );
                if(  !empty( $datas['ref_post_date'] ) )
                {
                    $posting_date = date( 'm/d/Y', strtotime( $datas['ref_post_date'] ) );
                    $datas['posting_date'] = $datas['ref_post_date'];
                }

                wcwh_form_field( $prefixName.'[posting_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Posting Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$posting_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['posting_date'] )? date( 'Y-m-d', strtotime( $datas['posting_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['ref_doc_id'] && $datas['delivery_doc'] )
                {
                    wcwh_form_field( $prefixName.'[delivery_doc]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'DO / DN No.', 'required'=>false, 'attrs'=>[], 'description'=>'Delivery Order No.' ], 
                        $datas['delivery_doc'], true 
                    );  
                    wcwh_form_field( $prefixName.'[delivery_doc]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                        $datas['delivery_doc'], $view
                    );  
                }
                else
                {
                    wcwh_form_field( $prefixName.'[delivery_doc]', 
                        [ 'id'=>'', 'label'=>'DO / DN No. / Delivery Ref No.', 'required'=>false, 'attrs'=>[],'class'=>[], 'description'=>'Delivery Order No.' ], 
                        $datas['delivery_doc'], $view 
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
                wcwh_form_field( $prefixName.'[delivery_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Delivery / Receiving Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['delivery_date'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
               wcwh_form_field( $prefixName.'[invoice]', 
                   [ 'id'=>'', 'label'=>'Invoice No.', 'required'=>false, 'attrs'=>[],'class'=>[], 'description'=>'Invoice No.' ], 
                   $datas['invoice'], $view 
               );  
            ?>
            </div>
            <div class="col form-group">
            <?php
                if( $args['setting'][ $args['section'] ]['use_auto_sales'] )
                {
                    $filter = [];
                    if( $args['seller'] ) $filter['seller'] = $args['seller'];
                    if( $args['setting'][ $args['section'] ]['auto_sales_client'] ) $filter['id'] = $args['setting'][ $args['section'] ]['auto_sales_client'];
                    $options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );

                    wcwh_form_field( $prefixName.'[client_automate_sale]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Client To Automate Sales Process', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                            'options'=> $options
                        ], 
                        ( $datas['client_automate_sale'] )? $datas['client_automate_sale'] : '', $view 
                    ); 
                }
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['ref_doc_id']  && $datas['purchase_doc'] )
                {
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'PR / PO No.', 'required'=>false, 'attrs'=>[], 'description'=>'Purchase Request / Purchase Order No.' ], 
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
                        [ 'id'=>'', 'label'=>'PR / PO No.', 'required'=>false, 'attrs'=>[],'class'=>[], 'description'=>'Purchase Request / Purchase Order No.' ], 
                        $datas['purchase_doc'], $view 
                    );  
                }
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['source_doc_type'] == 'transfer_order' )
                {
                    $filter = [ 'status'=>1 ];
                    $filter['not_id'] = $args['wh_id'];
                    $options = options_data( apply_filters( 'wcwh_get_warehouse', $filter, [], false, [] ), 'code', [ 'code', 'name' ] );
                    
                    if( $datas['ref_doc_id'] && $datas['supply_from_seller'] )
                    {
                        wcwh_form_field( $prefixName.'[supply_from_seller]', 
                            [ 'id'=>'', 'type'=>'text', 'label'=>'Outlet', 'required'=>false, 'attrs'=>[] ], 
                            $options[ $datas['supply_from_seller'] ], true 
                        );  
                        wcwh_form_field( $prefixName.'[supply_from_seller]', 
                            [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                            $datas['supply_from_seller'], $view
                        );  
                    }
                    else
                    {
                        wcwh_form_field( $prefixName.'[supply_from_seller]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'From Outlet', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                                'options'=> $options
                            ], 
                            ( $datas['supply_from_seller'] )? $datas['supply_from_seller'] : $datas['ref_warehouse'], $view 
                        ); 
                    }
                }
                else
                {
                    $filter = [];
                    if( $args['seller'] ) $filter['seller'] = $args['seller'];
                    $options = options_data( apply_filters( 'wcwh_get_supplier', $filter, [], false, 
                            [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
                    if( $datas['ref_doc_id'] && $datas['supplier_company_code'] )
                    {
                        wcwh_form_field( $prefixName.'[supplier_company_code]', 
                            [ 'id'=>'', 'type'=>'text', 'label'=>'Supplier', 'required'=>false, 'attrs'=>[] ], 
                            $options[ $datas['supplier_company_code'] ], true 
                        );  
                        wcwh_form_field( $prefixName.'[supplier_company_code]', 
                            [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                            $datas['supplier_company_code'], $view
                        );  
                    }
                    else
                    {
                        $sc = $args['setting'][ $args['section'] ]['default_supplier'];
                        wcwh_form_field( $prefixName.'[supplier_company_code]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'Supplier', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                                'options'=> $options
                            ], 
                            ( $datas['supplier_company_code'] )? $datas['supplier_company_code'] : $sc, $view 
                        ); 
                    }
                }
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

    <div class="detail-container">
        <h5>Details</h5>
        <?php if( ! $view  ): ?>
        <?php if( ! $args['NoAddItem'] ): ?>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                $filter = [ 'status'=>1, 'pricing'=>'yes' ];
                
                $purchaser = apply_filters( 'wcwh_get_warehouse', [ 'indication'=>1 ], [], true, [ 'usage'=>1 ] );
                if( $purchaser ) $filter['seller'] = $purchaser['code'];
                $items = apply_filters( 'wcwh_get_latest_purchase_price', $filter, [], false, [ 'usage'=>1, 'uom'=>1, 'isUnit'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                
                if( $items )
                {
					//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
					//----- probably no use as NoAddItem for GR -----//
                    $from_date = strtotime('-2 weeks', current_time( 'timestamp' ));
                    $from_date = date('Y-m-d H:i:s', $from_date);
                    $row_style ='style="background-color: #F5FFF5"';
                    //-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
					
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $items as $i => $item )
                    {   
                        $hc_uprice = "";
						$row_styling = ""; //-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
                        if( ! $datas['ref_doc_id'] )
                        {
                           $hc_uprice = $item['unit_price'];
                        }
						
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
						//----- probably no use as NoAddItem for GR -----//
						if( $datas['created_at'] && $datas['created_at'] >= $from_date ) $row_styling = $row_style;
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//

                        $fraction = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';
                        $inconsistent = ( $item['required_unit'] )? '' : 'readonly';
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-bqty=""
                            data-bunit="" 
                            data-uprice="'.$hc_uprice.'" 
                            data-total_amount="" 
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
                            data-foc="" 
                            data-prod_expiry=""
                            data-item_number=""
							data-row_styling = "'.$row_styling.'"
                        >'. $item['code'].', '.$item['uom_code'].', '.$item['name'] .'</option>';
						
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
                    }
                    echo '</select>';
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".pr-items" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <?php endif; ?>
        <style>
            table.details .expiration{ width:10%; }
        </style>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <th class="ref_qty" >Ref Qty <sup class="toolTip" title="Qty of reference document"> ? </sup></th>
                    <th class="balance">Bal <sup class="toolTip" title="Balance of reference document"> ? </sup></th>
                    <th class="qty">Qty</th>
                    <th class="unit">Metric (kg/l)</th>
                    <th class="unit_price">Unit Price</th>
                    <th class="unit_price">Total Amt</th>
                <?php if( $args['setting'][ $args['section'] ]['use_expiry'] ): ?>
                    <th class="unit_price expiration">Expiry</th>
                    <th class="action"></th>
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
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'product_id' => '{product_id}', 
                            'item_id' => '{item_id}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'bqty' => '{bqty}',
                            'bunit' => '{bunit}', 
                            'uprice' => '{uprice}', 
                            'ref_bqty' => '{ref_bqty}',
                            'ref_bal' => '{ref_bal}',
                            'ref_doc_id' => '{ref_doc_id}',
                            'ref_item_id' => '{ref_item_id}',
                            'fraction' => '{fraction}',
                            'inconsistent' => '{inconsistent}',
                            'readonly' => '{readonly}',
                            'total_amount' => '{total_amount}',
                            'foc' => '{foc}',
                            'prod_expiry' => '{prod_expiry}',
                            'item_number' => '{item_number}',
                            'fi' => '{fi}',
							'row_styling' => '{row_styling}',
                        ];
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//

                        $filter = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
						
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['item_id'],
                            'item' => $row['line_item']['code'].' - '.$row['line_item']['name'],
                            'uom' => $row['line_item']['uom_code'],
                            'bqty' => round_to( $row['bqty'], 2 ), 
                            'bunit' => round_to( $row['bunit'], 3 ), 
                            'uprice' => ( $row['uprice'] && $row['uprice'] > 0 )? round_to( $row['uprice'], 5 ) : 0, 
                            'ref_bqty' => round_to( $row['ref_bqty'], 2 ),
                            'ref_bal' => round_to( $row['ref_bal'], 2 ),
                            'ref_doc_id' => $row['ref_doc_id'],
                            'ref_item_id' => $row['ref_item_id'],
                            'fraction' => ( $row['line_item']['uom_fraction'] )? 'positive-number' : 'positive-integer',
                            'inconsistent' => ( $row['line_item']['required_unit'] )? '' : 'readonly',
                            'readonly' => ( ! current_user_cans( ['wh_admin_support'] ) )? 'readonly' : '',
                            'total_amount' => ( $row['total_amount'] && $row['total_amount'] > 0 )? round_to( $row['total_amount'], 2 ) : '',
                            'foc' => $row['foc'],
                            'prod_expiry' => $row['prod_expiry'],
                            'item_number' => $row['_item_number'],
                            'fi' => $row['fi'],
							'row_styling' => $row['row_styling'],
                        ];
						//-------- 7/9/22 jeff GoodRecpt Form Row Appearance -----//
                        $ag = $row;
                        $ag['setting'] = $args['setting'];
                        $ag['section'] = $args['section'];
                        $ag['expiryrow'] = $args['rowexpiryTpl'];
                        $ag['ref_doc_id'] = $datas['ref_doc_id'];
                        $ag['doc_date'] = $datas['doc_date'];

                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/goodReceive-row.php', $ag );
                        //if( ! empty( $row['parent_item_id'] ) )
                        //    $tpl = apply_filters( 'wcwh_get_template_content', 'segment/goodReceive-expiryRow.php', $ag );
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

    <?php if( $datas['doc_id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>