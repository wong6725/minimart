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
                $seller = apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'company'=>1 ] );
                $options = options_data( $seller, 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[seller][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Seller', 'required'=>true, 'attrs'=>[], 'class'=>['select2','modalSelect'],
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
                $schemes = get_schemes( 'pricing' );
                $options = options_data( $schemes, 'scheme', [ 'title' ], '' );
                
                if( ! $view )
                {
                    wcwh_form_field( $prefixName.'[scheme]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Price Apply To', 'required'=>false, 'attrs'=>['data-showhide=".scheme_ref"'], 'class'=>['optionShowHide'],
                            'options'=> $options
                        ], 
                        $datas['scheme'], $view 
                    ); 
                }
                else
                {
                    wcwh_form_field( $prefixName.'[scheme]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Price Apply To', 'required'=>false, 'attrs'=>[], 'class'=>[],
                            'options'=> $options
                        ], 
                        $datas['scheme'], $view 
                    ); 
                }
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );

                $options = options_data( apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'company'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[margin_source]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Margin Price Source (Seller)', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    ( $datas['margin_source'] )? $datas['margin_source'] : $wh['code'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group scheme_ref default">
            <?php 
                wcwh_form_field( $prefixName.'[default]', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    $datas['default'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group scheme_ref client_code">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1, 'warehouse'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[client_code][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Target Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['client_code'], $view 
                ); 
            ?>
            </div>
            <!-- <div class="col form-group scheme_ref customer_group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_customer_group', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[customer_group]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Target Customer Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['customer_group'], $view 
                ); 
            ?>
            </div>-->
            <div class="col form-group">
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'DEFAULT'=>'Default', 'ROUND'=>'Round Nearest', 'FLOOR'=>'Nearest Down', 'CEIL'=>'Nearest Up' ];

                wcwh_form_field( $prefixName.'[round_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Round Type', 'required'=>false, 'attrs'=>[], 'class'=>[],
                        'options'=> $options
                    ], 
                    $datas['round_type'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[round_nearest]', 
                    [ 'id'=>'', 'label'=>'Round Nearest', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 'description'=>'Nearest 0.1', 'placeholder'=>'0.1' ], 
                    $datas['round_nearest'], $view  
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
            </div>
        </div>
    </div>

    <div class="detail-container">
        <h5>Details</h5>
        <?php if( ! $view ): ?>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                $items = apply_filters( 'wcwh_get_item', [], [], false, [ 'usage'=>1, 'uom'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] );
                if( $items )
                {
                    echo '<select class="pr-items canScanBarcode select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Item(s)">';
                    
                    echo '<option 
                            value="0" 
                            data-id="0" 
                            data-product_id="0" 
                            data-price_value="" 
                            data-item_id="" 
                            data-uom="" 
                            data-sku="" 
                            data-code=""
                            data-serial="" 
                            data-item="All Items"
                            data-inconsistent=""  
                        >All Items</option>';

                    foreach( $items as $i => $item )
                    {   
                        $inconsistent = ( $item['inconsistent_unit'] )? 'Yes' : '-';
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-product_id="'.$item['id'].'" 
                            data-price_value="" 
                            data-item_id="" 
                            data-uom="'.$item['uom_code'].'" 
                            data-sku="'.$item['_sku'].'" 
                            data-code="'.$item['code'].'"
                            data-serial="'.$item['serial'].'" 
                            data-item="'.$item['code'].' - '.$item['name'].'"
                            data-inconsistent="'.$inconsistent.'"  
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
                    <th class="unit_price">Margin (%)</th>
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
                            'price_value' => '{price_value}', 
                            'product_id' => '{product_id}', 
                            'item_id' => '{item_id}',
                            'item' => '{item}',
                            'uom' => '{uom}',
                            'inconsistent' => '{inconsistent}',
                        ];

                        if( $row['product_id'] > 0 )
                        {
                            $row_item = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', [ 'id'=>$row['product_id'] ], [], true, [ 'uom'=>1, 'category'=>1 ] );
                        }
                        else
                            $row_item = $row['line_item'];
                        
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['product_id'], 
                            'price_value' => $row['price_value'], 
                            'product_id' => $row['product_id'], 
                            'item_id' => $row['id'],
                            'item' => ( ( $row_item['code'] )? $row_item['code'].' - ' : '' ).$row_item['name'],
                            'uom' => $row_item['uom_code'],
                            'inconsistent' => ( $row['line_item']['inconsistent_unit'] )? 'Yes' : '-',
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/margin-row.php' );
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