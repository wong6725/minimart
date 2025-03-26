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

<!-----------------------------------   Form Header            ------------------------------------------->
<!-----------------------------------   Form Header            ------------------------------------------->
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

                wcwh_form_field( $prefixName.'[ref_doc_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['ref_doc_id'], $view 
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
            ?>
            </div>
        </div>    
    </div>

<!-----------------------------------   End of Form Header            ------------------------------------------->
<!-----------------------------------   End of Form Header            ------------------------------------------->

<!-----------------------------------   Form Details            ------------------------------------------->
<!-----------------------------------   Form Details            ------------------------------------------->

<div class="detail-container">
        <h5>Details</h5>
        <?php if( ! $view  ): ?>
        <?php if( ! $args['NoAddItem'] ): ?>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                $filters = ['status'=>1];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];

                //---- reprocessed item 
                $reprocess_items_list = apply_filters( 'wcwh_get_reprocess_item', $filters, [], false, [ 'usage'=>1 ] );
                if( $reprocess_items_list )
                {
                    $reprocess_items = [];
                    $materials = [];

                    foreach( $reprocess_items_list as $i => $item )
                    {
                        $filters['id'] = $item['mat_id'];
                        $materials = apply_filters( 'wcwh_get_item', $filters, [], true, [ 'uom'=>1, 'isUnit'=>1, 'stocks'=>$datas['warehouse_id'], 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'], 'meta'=>['tolerance', 'tolerance_rounding']] );
                        if( $materials )
                        {
                            $stk = 0;
                            $tolerance_qty = 0;
                            $tolerance_pholder = '';
                            $inventory = [];
                            $fraction = ( $materials['uom_fraction'] )? 'positive-number' : 'positive-integer';
                            $inconsistent = ( $materials['required_unit'] )? '' : 'readonly';
                            $tolerance = ( $materials['tolerance'] )?  $materials['tolerance']: 0;
                            $tolerance_rounding = ( $materials['tolerance_rounding'] )?  $materials['tolerance_rounding']: 'default';

                            if( $materials['parent'] > 0 )
                            {
                                $temp_filters = ['wh_code'=>$this->warehouse['code'], 'sys_reserved'=>'staging'];
                                $storage = apply_filters( 'wcwh_get_storage', $temp_filters, [], true, [ 'usage'=>1 ] );

                                $temp_filters = [ 'wh_code'=>$this->warehouse['code'], 'strg_id'=>$storage['id'], 'item_id'=>$materials['parent'] ];
                                $stock = apply_filters( 'wcwh_get_stocks', $temp_filters, [], false, [ 'converse'=>1, 'tree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                                if($stock) $inventory = $stock;
                            }

                            $stk = ( $inventory['qty'] )? $inventory['qty'] : $materials['stock_qty'];
                            $stk-= ( $inventory['stock_allocated'] )? $inventory['allocated_qty'] : $materials['stock_allocated'];

                            $tolerance_pholder = floor( $stk / $item['required_qty']);
                            
                            if($tolerance && $item['required_qty'] && $stk)//----placeholder value
                            {
                                $t_value = $stk / $item['required_qty'];
                                if($tolerance_rounding && $t_value)
                                {
                                    if($tolerance_rounding =='default') $tolerance_qty = round( $t_value * ($tolerance/100));
                                    else if($tolerance_rounding =='ceil') $tolerance_qty = ceil( $t_value * ($tolerance/100));
                                    else if($tolerance_rounding =='floor') $tolerance = floor( $t_value * ($tolerance/100));

                                    $t_value = floor($t_value);

                                    $min = floor($t_value) - $tolerance_qty;
                                    if($min<0)
                                    {
                                        $min = 0;
                                    }
                                    $max = $t_value + $tolerance_qty;
                                    $tolerance_pholder = $min.' - '.$max;                      
                                }
                            }

                            $temp_reprocess_items = [
                                'id' => $item['items_id'],
                                'name' => $item['outcome'],
                                '_sku' => $item['out_sku'],
                                'code' => $item['out_code'],
                                'serial' => $item['out_serial'],
                                'uom_code' => $item['out_uom'],
                                'materials' => $item['mat_id'],
                                'material_name' => $item['material'],
                                'material_sku' => $item['mat_sku'],
                                'material_code' => $item['mat_code'],
                                'material_serial' => $item['mat_serial'],
                                'material_uom' => $item['mat_uom'],
                                'required_qty' => $item['required_qty'],
                                'material_stock' => $stk,
                                'tolerance' => $tolerance,
                                'tolerance_rounding' => $tolerance_rounding,
                                'tolerance_pholder' => $tolerance_pholder,
                                'fraction' => $fraction,
                                'inconsistent' => $inconsistent
                            ];
                            $reprocess_items[ $item['items_id'] ] = $temp_reprocess_items;
                        }
                    }
                    
                    echo '<select class="rep-items select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    if( $reprocess_items )
                    {
                        foreach( $reprocess_items as $i => $item )
                        {
                            echo '<option 
                                value="'.$item['id'].'"
                                data-id="'.$item['id'].'"
                                data-name="'.$item['name'].'"
                                data-sku="'.$item['_sku'].'"
                                data-code="'.$item['code'].'"
                                data-serial="'.$item['serial'].'"
                                data-uom="'.$item['uom_code'].'"
                                data-bqty = ""

                                data-mat_id="'.$item['materials'].'"
                                data-mat_name="'.$item['material_name'].'"
                                data-mat_sku="'.$item['material_sku'].'"
                                data-mat_code="'.$item['material_code'].'"
                                data-mat_serial="'.$item['material_serial'].'"
                                data-mat_uom="'.$item['material_uom'].'"
                                data-mat_req="'.$item['required_qty'].'"
                                data-mat_stock="'.round_to( $item['material_stock'], 2 ).'"
                                data-material_uqty ="'.round_to( $item['material_stock'], 2 ).'"

                                data-fraction="'.$item['fraction'].'" 
                                data-inconsistent="'.$item['inconsistent'].'"
                                data-tolerance="'.$item['tolerance'].'"
                                data-tolerance_rounding="'.$item['tolerance_rounding'].'"
                                data-tolerance_pholder="'.$item['tolerance_pholder'].'"

                                data-item="'.$item['code'].' - '.$item['name'].'"
                                data-mat="'.$item['material_code'].' - '.$item['material_name'].'"
                                data-mat_bqty="0"
                                data-mat_bunit="" 
                                data-mat_uprice=""
                                data-mat_ref_bqty="0" 
                                data-mat_ref_bal="0" 
                                data-mat_ref_doc_id=""
                                data-mat_ref_item_id=""
                                data-item_number=""

                                >'. $item['code'].', '.$item['serial'].', '.$item['uom_code'].', '.$item['name'] .'</option>';
                        }
                    }
                    echo '</select>';                    
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".rep-items" data-field=".pr-items" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <?php endif; ?>

        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="from_item">From Item</th>
                    <th class="uom">UOM</th>
                    <th class="stocks">Stocks</th>
                    <th class="ref_qty" >Ref Qty <sup class="toolTip" title="Qty of reference document"> ? </sup></th>
                    <th class="balance">Bal <sup class="toolTip" title="Balance of reference document"> ? </sup></th>
                    <th class="to_item">To Item</th>
                    <th class="convert_uom">To UOM</th>
                    <th class="qty">Quantity to be Processed</th>
                    <th class="bqty">Tolerance Quantity</th>
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
                            'mat' => '{mat}',
                            'mat_uom' => '{mat_uom}',
                            'mat_stock' => '{mat_stock}',
                            'mat_ref_bqty' => '{mat_ref_bqty}',
                            'mat_ref_bal' => '{mat_ref_bal}',
                            'material_uqty' => '{material_uqty}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'mat_id' => '{mat_id}',
                            'mat_req' => '{mat_req}',
                            'item_id' => '{item_id}',
                            'ref_doc_id' => '{ref_doc_id}',
                            'ref_item_id' => '{ref_item_id}',
                            'ref_bal' => '{ref_bal}',
                            'item_number' => '{item_number}',
                            'bqty' => '{bqty}',
                            'tolerance' => '{tolerance}',
                            'tolerance_rounding' => '{tolerance_rounding}',
                            'tolerance_pholder' => '{tolerance_pholder}',
                            'fraction' => '{fraction}'                            
                        ];

                        $filters = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filters['seller'] = $args['seller'];
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filters, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1, 'meta'=>['tolerance', 'tolerance_rounding'] ] );

                        $replace = [
                            'i' => $i,
                            'id' => $row['id'],
                            'mat' => $row['mat'],
                            'mat_uom' => $row['mat_uom'],
                            'mat_stock' => $row['mat_stock'],
                            'mat_ref_bqty' => $row['ref_bqty'],
                            'mat_ref_bal' => $row['ref_bal'],
                            'material_uqty' => $row['material_uqty'],
                            'item' => $row['item'],
                            'uom' => $row['uom'],
                            'mat_id' => $row['mat_id'],
                            'mat_req' => $row['mat_req'],
                            'item_id' => $row['item_id'],
                            'ref_doc_id' => $row['ref_doc_id'],
                            'ref_item_id' => $row['ref_item_id'],
                            'ref_bal' => $row['ref_bal'],
                            'item_number' => $row['_item_number'],
                            'bqty' => $row['bqty'],
                            'tolerance' => $row['tolerance'],
                            'tolerance_rounding' => $row['tolerance_rounding'],
                            'tolerance_pholder' => $row['tolerance_pholder'],
                            'fraction' => $row['fraction'],
                        ];

                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/reprocess-rowv2.php', $row );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }

                }
                ?>
            </tbody>
        </table>

        <!-----------------------------------   if $View   ----------------------------------->        
        <?php else: ?>
             <div class="form-row">
                <div class="col form-group">
                <?php 
                    echo $args['render'];
                ?>
                </div>
            </div>
        <?php endif; ?>
        <!-----------------------------------   End if $View   -------------------------------------->

    </div>
<!-----------------------------------   End of Form Details            ------------------------------------------->
<!-----------------------------------   End of Form Details            ------------------------------------------->



    <?php if( $datas['doc_id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>

<script>
jQuery(function($){
    $( document ).on( 'change', '.tolerance_validate', function()
    {
        let tolerance = $( this ).data( 'tolerance' );
        let tolerance_rounding = $( this ).data( 'tolerance_rounding' );
        let num = $(this).data('i');        
        let input_v = $(this).val();

        let req_qty = $(this).closest('tr.row'+num).find('td [name="_detail['+num+'][material_req]"]').val();
        let qty = $(this).closest('tr.row'+num).find('td [name="_detail['+num+'][material_uqty]"]').val();

        if(req_qty && qty)
        {
            let tolerance_qty = 0;
            let min = 0;
            let max = 0;
            let temp = qty/req_qty;
            if(tolerance_rounding == 'default')
            {
                tolerance_qty = Math.round(temp * (tolerance/100));
            }
            else if(tolerance_rounding =='ceil')
            {
                tolerance_qty = Math.ceil(temp * (tolerance/100));            
            }
            else if(tolerance_rounding =='floor')
            {
                tolerance_qty = Math.floor(temp * (tolerance/100));            
            }

            temp= Math.floor(temp);
            min = temp - tolerance_qty;
            if(min < 0) min = 0;
            max = temp + tolerance_qty;

            if( !(input_v >= min && input_v <= max) )
            {
                $(this).val('');
                return false;                
            }
        }
        else
        {
            $(this).val('');
            return false;
        }

    });

    $( document ).on( 'change', '.tolerance_counter', function()
    {
        let num = $(this).data('i');  
        let input_v = $(this).val();
        $(this).closest('tr.row'+num).find('td [name="_detail['+num+'][bqty]"]').val('');

        let tolerance = parseFloat($(this).closest('tr.row'+num).find('td [name="_detail['+num+'][bqty]"]').data( 'tolerance' ));
        let tolerance_rounding = $(this).closest('tr.row'+num).find('td [name="_detail['+num+'][bqty]"]').data( 'tolerance_rounding' );
        let stk = parseFloat($(this).closest('tr.row'+num).find('td.material_stock').html());
        let req_qty = parseFloat($(this).closest('tr.row'+num).find('td [name="_detail['+num+'][material_req]"]').val());

        if(input_v > stk)
        {
            $(this).val('');
            return false;
        }
        else if(input_v < req_qty)
        {
            $(this).val('');
            return false;
        }
        else
        {
            if(req_qty)
            {
                let tolerance_qty = 0;
                let min = 0;
                let max = 0;
                let temp = input_v/req_qty;
                if(tolerance_rounding == 'default')
                {
                    tolerance_qty = Math.round(temp * (tolerance/100));
                }
                else if(tolerance_rounding =='ceil')
                {
                    tolerance_qty = Math.ceil(temp * (tolerance/100));            
                }
                else if(tolerance_rounding =='floor')
                {
                    tolerance_qty = Math.floor(temp * (tolerance/100));            
                }

                temp= Math.floor(temp);                
                min = temp - tolerance_qty;
                if(min < 0) min = 0;
                max = temp + tolerance_qty;

                $(this).closest('tr.row'+num).find('td [name="_detail['+num+'][bqty]"]').attr("placeholder", min+" - "+max);
            }
        }
    });


});
</script>