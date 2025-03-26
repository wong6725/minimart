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
                wcwh_form_field( $prefixName.'[order_no]', 
                    [ 'id'=>'', 'label'=>'Order No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['order_no'], true
                ); 

                wcwh_form_field( $prefixName.'[ref_doc_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['ref_doc_id'], true
                ); 
                wcwh_form_field( $prefixName.'[wh_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['wh_id'], true
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['order_date'] ) $order_date = date( 'm/d/Y', strtotime( $datas['order_date'] ) );

                wcwh_form_field( $prefixName.'[order_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Order Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$order_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['order_date'] )? date( 'Y-m-d', strtotime( $datas['order_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = wc_get_order_statuses();
                
                wcwh_form_field( $prefixName.'[order_status]', 
                    [ 'id'=>'', 'label'=>'Order Status', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                     $options[$datas['order_status']], true 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'] ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer', $filter ), 'id', [ 'code', 'uid', 'name' ] );
                
                wcwh_form_field( $prefixName.'[customer_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['customer_id'], $view 
                ); 
            ?>
            </div>
        </div>

        <?php if( current_user_cans( [ 'wh_admin_support' ] ) ): ?>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_pos_register', 0, $args['seller'] ), 'ID', [ 'slug', 'name' ] );

                wcwh_form_field( $prefixName.'[register]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Register', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    $datas['register'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                wcwh_form_field( $prefixName.'[session_id]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Session', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                    $datas['session_id'], $view 
                ); 
            ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[total]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Total', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 
                        'placeholder'=>$datas['total'] ], 
                    '', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[total_credit]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Total Credited', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 
                        'placeholder'=>$datas['total_credit'] ], 
                    '', $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[amt_paid]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Amount Paid', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 
                        'placeholder'=>$datas['amt_paid'] ], 
                    '', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                wcwh_form_field( $prefixName.'[amt_change]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Amount Change', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 
                        'placeholder'=>$datas['amt_change'] ], 
                    '', $view 
                ); 
            ?>
            </div>
        </div>
    
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[order_comments]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Order Remark', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                        'placeholder'=>$datas['order_comments'] ], 
                    $datas['order_comments'], $view 
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
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $arg = [ 'uom'=>1, 'isUnit'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ];
                $items = apply_filters( 'wcwh_get_item', $filter, [], false, $arg );
                if( $items )
                {
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $items as $i => $item )
                    {   
                        $fraction = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';
                        $inconsistent = ( $item['inconsistent_unit'] )? '' : 'readonly';
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-item="'.$item['code'].' - '.$item['name'].'" 
                            data-uom="'.$item['uom_code'].'" 
                            data-price_code="" 
                            data-uprice="" 
                            data-price="" 
                            data-item_id="" 
                            data-qty=""
                            data-metric="" 
                            data-fraction="'.$fraction.'" 
                            data-inconsistent="'.$inconsistent.'" 
                        >'.$item['code'].', '.$item['uom_code'].', '.$item['name'].'</option>';
                    }
                    echo '</select>';
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-repeative="1" data-source=".pr-items" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <th class="uom">Price Code</th>
                    <th class="unit_price">Unit Price</th>
                    <th class="unit_price">Item Price</th>
                    <th class="unit_price">Qty</th>
                    <th class="unit_price">Metric (kg/l)</th>
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
                            'product_id' => '{product_id}', 
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'price_code' => '{price_code}', 
                            'uprice' => '{uprice}', 
                            'price' => '{price}', 
                            'item_id' => '{item_id}',
                            'qty' => '{qty}',
                            'metric' => '{metric}',
                            'fraction' => '{fraction}',
                            'inconsistent' => '{inconsistent}',
                        ];
                        
                        $filter = [ 'id'=>$row['item_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $arg = [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ];
                        $item = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, $arg );

                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['item_id'], 
                            'product_id' => $row['item_id'], 
                            'item' => ( $row['prdt_name'] )? $row['prdt_name'] : $item['code'].' - '.$item['name'],
                            'uom' => $row['uom'],
                            'price_code' => $row['price_code'], 
                            'uprice' => $row['uprice'], 
                            'price' => $row['price'], 
                            'item_id' => $row['id'],
                            'qty' => $row['qty'],
                            'metric' => $row['metric'],
                            'fraction' => ( $row['line_item']['uom_fraction'] )? 'positive-number' : 'positive-integer',
                            'inconsistent' => ( $row['line_item']['required_unit'] )? '' : 'readonly',
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/posCDN-row.php', [] );
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

    <?php if( $datas['id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>