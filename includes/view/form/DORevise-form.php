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
                if( $args['ref_doc_type'] == 'transfer_order' )
                {
                    $filter = [ 'status'=>1 ];
                    $filter['not_id'] = $args['wh_id'];
                    $options = options_data( apply_filters( 'wcwh_get_warehouse', $filter, [], false, [] ), 'code', [ 'code', 'name' ] );
                    
                    if( $datas['ref_doc_id'] && $datas['supply_to_seller'] )
                    {
                        wcwh_form_field( $prefixName.'[supply_to_seller]', 
                            [ 'id'=>'', 'type'=>'text', 'label'=>'Outlet', 'required'=>false, 'attrs'=>[] ], 
                            $options[ $datas['supply_to_seller'] ], true 
                        );  
                        wcwh_form_field( $prefixName.'[supply_to_seller]', 
                            [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                            $datas['supply_to_seller'], $view
                        );  
                    }
                    else
                    {
                        wcwh_form_field( $prefixName.'[supply_to_seller]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'Outlet', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                                'options'=> $options
                            ], 
                            ( $datas['supply_to_seller'] )? $datas['supply_to_seller'] : '', $view 
                        ); 
                    }
                }
                else
                {
                    $filter = [];
                    if( $args['seller'] ) $filter['seller'] = $args['seller'];
                    $options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, 
                            [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
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
                        wcwh_form_field( $prefixName.'[client_company_code]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'Client', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                                'options'=> $options
                            ], 
                            $datas['client_company_code'], $view 
                        ); 
                    }
                }
                wcwh_form_field( $prefixName.'[ref_doc_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['ref_doc_id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
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
        <?php if( ! $args['NoAddItem'] ): ?>
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
                            data-uprice="" 
                            data-foc="" 
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
                            data-item_number=""
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
        <?php endif; ?>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <th class="ref_qty" >Ref Qty <sup class="toolTip" title="Qty of reference document"> ? </sup></th>
                    <th class="balance">Bal <sup class="toolTip" title="Balance of reference document"> ? </sup></th>
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
                            'item_number' => '{item_number}',
                            'uprice' => '{uprice}',
                            'total_amount' => '{total_amount}',
                        ];

                        $filter = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['item_id'],
                            'item' => $row['line_item']['code'].' - '.$row['line_item']['name'],
                            'uom' => $row['line_item']['uom_code'],
                            'bqty' => round_to( $row['bqty'], 2 ), 
                            'bunit' => round_to( $row['bunit'], 3 ), 
                            'uprice' => round_to( $row['uprice'], 5 ), 
                            'ref_bqty' => round_to( $row['ref_bqty'], 2 ),
                            'ref_bal' => round_to( $row['ref_bal'], 2 ),
                            'ref_doc_id' => $row['ref_doc_id'],
                            'ref_item_id' => $row['ref_item_id'],
                            'fraction' => ( $row['line_item']['uom_fraction'] )? 'positive-number' : 'positive-integer',
                            'inconsistent' => ( $row['line_item']['required_unit'] )? '' : 'readonly',
                            'item_number' => $row['_item_number'],
                            'uprice' => $row['uprice'],
                            'total_amount' => $row['total_amount'],
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/DORevise-row.php' );
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