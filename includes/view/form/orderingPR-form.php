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
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $view && empty( $datas['posting_date'] ) ) $datas['posting_date'] = $datas['post_date'];
                if( $datas['posting_date'] ) $posting_date = date( 'm/d/Y', strtotime( $datas['posting_date'] ) );

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
                wcwh_form_field( $prefixName.'[remark]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remark'], $view 
                ); 

                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['warehouse_id'], $view 
                );
                //---------------------hidden field: ordering meta
                wcwh_form_field( $prefixName.'[ordering_ref]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['ordering_ref'], $view 
                ); 
                //-----------------hidden field: ordering meta
            ?>
            </div>
        </div>
    
    </div>

    <div class="detail-container">
        <h5>Details</h5>
        <?php if( ! $view ): ?>
        <div class="actions row">
            <div class="col-md-10">
            <?php
                //--------- 14/9/22 ROV
                if(!$datas['reorder_item_info'])
                {
                    if( $datas['warehouse_id'])
                        $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$datas['warehouse_id'] ], [], true, [ 'company'=>1 ] );

                    $filters['seller'] = ($warehouse['id'])?$warehouse['id']: '';
                    include_once( WCWH_DIR . "/includes/reports/reorderReport.php" );
                    $RR = new WCWH_Reorder_Rpt();
                    $reorder_item_info = $RR->get_reorder_report( $filters, [], [ 'uom'=>1] );
                }
                else
                {
                    $reorder_item_info= $datas['reorder_item_info'];
                }

                if( $reorder_item_info )
                {
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect immediate" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $reorder_item_info as $i => $item )
                    {
                        $hms = $item['hms_qty'];
                        if($item['hms_month']) $hms = $hms.'<br> ('.$item['hms_month'].')';                           
                        $fraction = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';

                        if( !$item['uom_fraction']) $bqty = ceil($item['final_rov']);
                        else $bqty = $item['final_rov'];

                        echo '<option 
                            value="'.$item['item_id'].'" 
                            data-id="'.$item['item_id'].'" 
                            data-product_id="'.$item['item_id'].'" 
                            data-bqty="'.$bqty.'" 
                            data-sku="'.$item['item_sku'].'" 
                            data-code="'.$item['item_code'].'"
                            data-serial="'.$item['item_serial'].'" 
                            data-item_id="" 
                            data-uom="'.$item['uom_code'].'" 
                            data-fraction="'.$fraction.'" 
                            data-item="'.$item['item_code'].' - '.$item['item_name'].'"
                            data-item_number=""
                            data-order_type="'.$item['order_type'].'"
                            data-stock_bal="'.$item['stock_bal'].'"
                            data-hms="'.$hms.'"
                            data-po_qty="'.$item['po_qty'].'"
                            data-rov="'.$item['final_rov'].'"
                            data-ref_bqty = ""
                        >'. $item['item_code'].', '.$item['uom_code'].', '.$item['item_name'] .'</option>';
                    }
                    echo '</select>';
                }
                //--------- 14/9/22 ROV
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".pr-items" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <!--------- 14/9/22 ROV --->
                    <th >Order Type</th>
                    <th >Stock</th>
                    <th >Ref Qty<sup class="toolTip" title="Reference PR Qty"> ? </sup></th>
                    <th >HMS Qty<sup class="toolTip" title="Highest Monthly Sales"> ? </sup></th>
                    <th class="ref_qty" >PO Qty<sup class="toolTip" title="Purchsae Order Pending Quantity"> ? </sup></th>
                    <th class="ref_qty" >ROV <sup class="toolTip" title="Final Recommend Order Volume"> ? </sup></th>
                    <!--------- 14/9/22 ROV --->
                    <th class="qty">Qty</th>
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
                            'bqty' => '{bqty}', 
                            'product_id' => '{product_id}', 
                            'item_id' => '{item_id}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'fraction' => '{fraction}',
                            'item_number' => '{item_number}',
                            //--------- 14/9/22 ROV
                            'order_type' => '{order_type}',
                            'stock_bal' => '{stock_bal}',
                            'hms' => '{hms}',
                            'po_qty' => '{po_qty}',
                            'rov' => '{rov}',
                            //--------- 14/9/22 ROV
                            'ref_bqty' => '{ref_bqty}',
                        ];

                        $filter = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1 ] );
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'bqty' => round_to( $row['bqty'], 2 ), 
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['item_id'],
                            'item' => $row['line_item']['code'].' - '.$row['line_item']['name'],
                            'uom' => $row['line_item']['uom_code'],
                            'fraction' => ( $row['line_item']['uom_fraction'] )? 'positive-number' : 'positive-integer',
                            'item_number' => $row['_item_number'],
                            //--------- 14/9/22 ROV
                            'order_type' => $row['order_type'],
                            'stock_bal' => ( $row['stock_bal'] )? $row['stock_bal']:'',
                            'hms' => ( $row['hms'] )? $row['hms']:'',
                            'po_qty' => ( $row['po_qty'] )? $row['po_qty']:'',
                            'rov' => ( $row['rov'] )? $row['rov']:'',
                            //--------- 14/9/22 ROV
                            'ref_bqty' => ( $row['ref_bqty'] )? $row['ref_bqty']:'',
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/orderingPR-row.php' );
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