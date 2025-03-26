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
                if( $datas['post_date'] ) $post_date = date( 'm/d/Y', strtotime( $datas['post_date'] ) );

                wcwh_form_field( $prefixName.'[post_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Posting Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$post_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['post_date'] )? date( 'Y-m-d', strtotime( $datas['post_date'] ) ) : "", $view 
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

    <div class="detail-container">
        <h5>Details</h5>
        <?php if( ! $view ): ?>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                $filter = [ 'status'=>1 ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $items = apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'isUnit'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                if( $items )
                {
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $items as $i => $item )
                    {   
                        $fraction = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';
                        $inconsistent = ( $item['required_unit'] )? '' : 'readonly';
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-bqty="" 
                            data-bunit="" 
                            data-item_id="" 
                            data-uom="'.$item['uom_code'].'" 
                            data-fraction="'.$fraction.'" 
                            data-inconsistent="'.$inconsistent.'" 
                            data-sku="'.$item['_sku'].'" 
                            data-code="'.$item['code'].'"
                            data-serial="'.$item['serial'].'" 
                            data-item="'.$item['code'].' - '.$item['name'].'"
                        >'.$item['code'].', '.$item['uom_code'].', '.$item['name'].'</option>';
                    }
                    echo '</select>';
                }
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
                    <th class="ref_qty" >Adjust <sup class="toolTip" title="Adjustment Type"> ? </sup></th>
                    <th class="qty">Qty</th>
                    <th class="unit">Metric (kg/l)</th>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="item_row">
            <?php
                if( $datas['details'] )
                {
                    foreach( $datas['details'] as $i => $row )
                    {
                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'bqty' => '{bqty}', 
                            'bunit' => '{bunit}', 
                            'product_id' => '{product_id}', 
                            'item_id' => '{item_id}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'fraction' => '{fraction}',
                            'plus_sign' => '{plus_sign}',
                            'inconsistent' => '{inconsistent}',
                        ];

                        $filter = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'bqty' => round_to( $row['bqty'], 2 ), 
                            'bunit' => round_to( $row['bunit'], 3 ),
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['item_id'],
                            'item' => $row['line_item']['code'].' - '.$row['line_item']['name'],
                            'uom' => $row['line_item']['uom_code'],
                            'fraction' => ( $row['line_item']['uom_fraction'] )? 'positive-number' : 'positive-integer',
                            'plus_sign' => $row['plus_sign'],
                            'inconsistent' => ( $row['line_item']['required_unit'] )? '' : 'readonly',
                        ];
                       
                        //$tpl = apply_filters( 'wcwh_get_template_content', 'segment/adjustment-row.php', $row );
                        //echo $tpl = str_replace( $find, $replace, $tpl );
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