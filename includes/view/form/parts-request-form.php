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
                    $datas['docno'], 1 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );
                else $doc_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $not_acc_type = $args['setting']['wh_customer']['non_editable_by_acc_type'];
                $filter = [ 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                //if( $not_acc_type ) $filter['not_acc_type'] = $not_acc_type;

                $options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'account'=>1, 'incl'=>$datas['customer_id'] ] ), 'id', [ 'uid', 'code', 'acc_name', 'name' ], '' );

                wcwh_form_field( $prefixName.'[customer_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Receiver', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'], 
                        'options'=> $options 
                    ], 
                    $datas['customer_id'], $view 
                ); 
                
                wcwh_form_field( $prefixName.'[customer_code]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['customer_code'], $view
                ); 
                wcwh_form_field( $prefixName.'[customer_uid]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['customer_uid'], $view
                ); 
                wcwh_form_field( $prefixName.'[acc_code]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['acc_code'], $view
                ); 
                wcwh_form_field( $prefixName.'[wh_code]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['wh_code'], $view
                ); 
            ?>
            </div>
            <!--<div class="col form-group">
            <?php
                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'label'=>'Estate', 'required'=>false, 'attrs'=>[] ], 
                    ( $datas['warehouse_id'] != $datas['wh_code'] )? $datas['wh_code'] : $datas['warehouse_id'], 1 
                );
            ?>
            </div>-->
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
                $filter = [ 'wh_code'=>$datas['warehouse_id'], 'sys_reserved'=>'staging' ];
                $storage = apply_filters( 'wcwh_get_storage', $filter, [], true, [ 'usage'=>1 ] );

                $filter = [ 'status'=>1 ];
                $filter['grp_id'] = $args['setting'][ $args['section'] ]['used_item_group'];
                if( $args['wh_code'] ) $filter['seller'] = $args['wh_code'];
                $items = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'usage'=>1, 'uom'=>1, 'group'=>1, 'category'=>1, 'inventory'=>$storage['id'] ] );
                
                if( $items )
                {
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect immediate" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $items as $i => $item )
                    {
                        $stk = $item['stock_qty'] - $item['stock_allocated'];
                        $fraction = ( $item['uom_fraction'] )? 'positive-number' : 'positive-integer';

                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-bqty="" 
                            data-sku="'.$item['sku'].'" 
                            data-code="'.$item['code'].'"
                            data-serial="'.$item['serial'].'" 
                            data-item_id="" 
                            data-uom="'.$item['uom_code'].'" 
                            data-fraction="'.$fraction.'" 
                            data-item="'.$item['code'].' - '.$item['name'].'"
                            data-item_number=""
                            data-period=""
                            data-def_price="'.$item['unit_price'].'"
                            data-sprice="'.$item['unit_price'].'"
                            data-stocks="'.round_to( $stk, 2 ).'"
                            data-grp_code="'.$item['grp_code'].'"
                            data-sale_amt=""
                        >'. $item['code'].', '.$item['uom_code'].', '.$item['name'] .'</option>';
                    }
                    echo '</select>';
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".pr-items"  data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <th class="stocks">Stocks</th>
                    <th class="qty">Price</th>
                    <th class="qty">Qty</th>
                    <th class="qty">Amt</th>
                    <!--<th class="qty">Installment (Month)</th>-->
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="item_row" class="sortable_row">
            <?php
                if( $datas['details'] )
                {
                    $tbqty = 0; $tamt = 0;
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
                            'period' => '{period}',
                            'def_price' => '{def_price}',
                            'sprice' => '{sprice}',
                            'stocks' => '{stocks}',
                            'grp_code' => '{grp_code}',
                            'sale_amt' => '{sale_amt}',
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
                            'period' => $row['period'],
                            'def_price' => ( $row['sprice'] )? $row['sprice'] : $row['unit_price'],
                            'sprice' => ( $row['sprice'] )? $row['sprice'] : $row['unit_price'],
                            'stocks' => round_to( $row['stocks'], 2 ),
                            'grp_code' => strtoupper( $row['line_item']['grp_code'] ),
                        ];
                        $replace['sale_amt'] = ( $row['sale_amt'] )? $row['sale_amt'] : $replace['bqty'] * $replace['sprice'];
                        $arg = $replace;
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/partsRequest-row.php', $arg );
                        echo $tpl = str_replace( $find, $replace, $tpl );

                        $tbqty+= round_to( $replace['bqty'], 2 );
                        $tamt+= round_to( $replace['sale_amt'], 2 );
                    }
                }
            ?>
            </tbody>
            <tfoot>
                <tr class="calc_result">
                    <th class="num"></th>
                    <th class="item">Total:</th>
                    <th class="uom"></th>
                    <th class="stocks"></th>
                    <th class="qty"></th>
                    <th class="qty tqty"><?php echo $tbqty; ?></th>
                    <th class="qty tprice"><?php echo round_to( $tamt, 2, 1, 1 ); ?></th>
                    <!--<th class="qty"></th>-->
                    <th class="action"></th>
                </tr>
            </tfoot>
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

<script>
jQuery(function($){
    $( document ).on( 'keyup change', '.details .calc_source .calc_qty', function()
    {
        var elem = $( this );
        var tqty = 0
        var tamt = 0;

        elem.parents( 'tbody' ).find( 'tr.calc_source' ).each( function( index ) 
        {
            var erow = $( this );
            var bqty = erow.find( '.calc_qty' ).val();
            var sprice = erow.find( '.calc_price' ).val();
            bqty = ( typeof bqty !== 'undefined' && bqty )? bqty : 0;
            sprice = ( typeof sprice !== 'undefined' && sprice )? sprice : 0;
            var amt = parseFloat(bqty) * parseFloat(sprice);
            
            erow.find( '.calc_amt' ).html(amt.toFixed(2));
            
            tqty+= parseFloat(bqty);
            tamt+= parseFloat(amt);
        });

        $( '.calc_result .tprice' ).html(tamt.toFixed(2));
        $( '.calc_result .tqty' ).html(tqty);
    });

    //hardcode
    $(document).on('DOMNodeInserted', function(e) {
        if ( $(e.target).hasClass('tr_row') ) {
            var elem = $(e.target).find( 'select.tr_mth' );
            if( elem.hasClass( 'grp_T' ) )
            {
                elem.find("option[value='6']").remove();
            }
        }
    });

});
</script>