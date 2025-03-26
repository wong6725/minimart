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
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                    $datas['docno'], ( $args['action'] == 'save' )? 1 : $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[code]', 
                    [ 'id'=>'', 'label'=>'Price Code', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'], 'description'=>'System generate' ], 
                    $datas['code'], true 
                ); 
            ?>
			<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $seller = apply_filters( 'wcwh_get_warehouse', [ 'indication'=>1 ], [], false, [ 'usage'=>1, 'company'=>1, 'nohide'=>1 ] );
                
                $options = options_data( $seller, 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[seller][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Purchaser', 'required'=>true, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['seller'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['since'] ) $since = date( 'm/d/Y', strtotime( $datas['since'] ) );

                wcwh_form_field( $prefixName.'[since]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective Date', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$since.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['since'] )? date( 'Y-m-d', strtotime( $datas['since'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[remarks]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remarks'], $view 
                ); 
            ?>

            <?php 
                wcwh_form_field( $prefixName.'[scheme]', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    'default', $view 
                ); 

                wcwh_form_field( $prefixName.'[default]', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    $datas['default'], $view 
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
                $arg = [ 'usage'=>1, 'uom'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ];
                $warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
                $strg_id = ( $args['inventory'] )? $args['inventory'] : 0;
                if( ! $strg_id && $warehouse && ! $warehouse['parent'] )
                {
                    $strg_id = apply_filters( 'wcwh_get_system_storage', 0, [ 'warehouse_id'=>$warehouse['code'], 'doc_type'=>'pricing' ] );
                }
                if( $strg_id ) $arg['inventory'] = $strg_id;
                $items = apply_filters( 'wcwh_get_item', [], [], false, $arg );
                if( $items )
                {
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    foreach( $items as $i => $item )
                    {   
                        $inconsistent = ( $item['inconsistent_unit'] )? 'Yes' : '-';
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-unit_price="" 
                            data-item_id="" 
                            data-uom="'.$item['uom_code'].'" 
                            data-sku="'.$item['_sku'].'" 
                            data-code="'.$item['code'].'"
                            data-serial="'.$item['serial'].'" 
                            data-item="'.$item['code'].' - '.$item['name'].'"
                            data-inconsistent="'.$inconsistent.'" 
                            data-avg_cost="'.$item['avg_cost'].'" 
                            data-latest_cost="'.$item['latest_cost'].'" 
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
                    <th class="uom">Inconsistent</th>
                    <?php if( $strg_id ): ?>
                    <th class="unit_price">Average Cost</th>
                    <th class="unit_price">Latest Cost</th>
                    <?php endif; ?>
                    <th class="unit_price">Unit Price</th>
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
                            'unit_price' => '{unit_price}', 
                            'product_id' => '{product_id}', 
                            'item_id' => '{item_id}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'inconsistent' => '{inconsistent}',
                            'avg_cost' => '{avg_cost}',
                            'latest_cost' => '{latest_cost}',
                        ];

                        $arg = [ 'uom'=>1, 'category'=>1 ];
                        if( $strg_id ) $arg['inventory'] = $strg_id;
                        $item = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, $arg );
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'unit_price' => $row['unit_price'], 
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['id'],
                            'item' => $item['code'].' - '.$item['name'],
                            'uom' => $item['uom_code'],
                            'inconsistent' => ( $row['line_item']['inconsistent_unit'] )? 'Yes' : '-',
                            'avg_cost' => $item['avg_cost'],
                            'latest_cost' => $item['latest_cost'],
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/purchasePricing-row.php', [ 'inventory'=>$strg_id ] );
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