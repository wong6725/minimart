<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_item';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>
<div class='form-rows-group'>
    <h5>Header <sup class="toolTip" title="Header / main inputs"> ? </sup></h5>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[name]', 
                [ 'id'=>'', 'label'=>'Item Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[label_name]', 
                [ 'id'=>'', 'label'=>'Short Name for Label', 'required'=>false, 'attrs'=>[ 'maxlength="18"' ] ], 
                $datas['label_name'], $view 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[chinese_name]', 
                [ 'id'=>'', 'label'=>'Chinese Name', 'required'=>false, 'attrs'=>[] ], 
                $datas['chinese_name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[indo_name]', 
                [ 'id'=>'', 'label'=>'Indonesian Name', 'required'=>false, 'attrs'=>[] ], 
                $datas['indo_name'], $view 
            ); 
        ?>
        </div>
    </div>
</div>

<div class='form-rows-group'>
    <h5>Item QR / Barcode No. <sup class="toolTip" title="At least 1 is needed"> ? </sup></h5>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            $gtin = [];
            $gtin[] = $datas['_sku'];
            if( $datas['serial2'] && $view )
            {
                foreach( $datas['serial2'] as $i => $row )
                {
                    $gtin[] = $row;
                }

                $datas['_sku'] = implode( ', ', $gtin );
            }

            wcwh_form_field( $prefixName.'[_sku]', 
                [ 'id'=>'', 'label'=>'SKU / GTIN No. (Primary)', 'required'=>false, 'attrs'=>[], 'description'=>'Barcode No. found on most item' ], 
                $datas['_sku'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Item Code (Secondary)', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[serial]', 
                [ 'id'=>'', 'label'=>'Barcode (Tertiary)', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'], 'description'=>'System generate' ], 
                $datas['serial'], true 
            ); 
        ?>
        </div>
    </div>
    <?php if( ! $view ): ?>
    <div class="form-row">
        <div class="col form-group">
            <a class="btn btn-sm btn-primary dynamic-element" data-tpl="<?php echo $args['rowTpl'].'TPL'; ?>" data-target="#item_row" data-serial2="" >
                Add Extra GTIN No. +
            </a>
            
            <div id="item_row">
                <br>
            <?php
                if( $datas['serial2'] )
                {
                    foreach( $datas['serial2'] as $i => $row )
                    {
                        $find = [ 
                            'serial2' => '{serial2}', 
                        ];

                        $replace = [ 
                            'serial2' => $row, 
                        ];
                       
                        $row['isView'] = $view;
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/item_sku-row.php', $row );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }
                }
            ?>
            </div>
        </div>
        <div class="col form-group">
        </div>
        <div class="col form-group">
        </div>
    </div>
    <?php endif; ?>
</div>

<div class='form-rows-group'>
    <h5>Unit Of Measurement (UOM)</h5>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $item_options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1 ] ), 'id', [ 'code', 'uom_code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Base Item', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $item_options, 'description'=>"Base item which has lower Unit of Measurement"
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_uom', $filter, [], false, [] ), 'code', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[_uom_code]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Current Item UOM', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>"UOM apply on current item"
                    ], 
                    $datas['_uom_code'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                //$options = options_data( apply_filters( 'wcwh_get_uom', [], [], false, [] ), 'code', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[_content_uom]', 
                    [ 'id'=>'_content_uom', 'type'=>'select', 'label'=>'Content UOM', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>'UOM apply to internal content'
                    ], 
                    $datas['_content_uom'], $view 
                ); 
            ?>
        </div>
    </div>
    <div class="form-row">

        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[_self_unit]', 
                    [ 'id'=>'', 'label'=>'Current Item Unit', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'],
                    'description'=>'Current item unit for conversion', 'placeholder'=>'' ], 
                    ( isset( $datas['_self_unit'] ) && $datas['_self_unit'] != '' )? $datas['_self_unit'] : 1, $view 
                ); 
            ?>
            <p class="description">Eg. '1' CTN of 500ml water</p>
            <p class="description">Eg. '1' PAC of vegetable</p>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[_parent_unit]', 
                    [ 'id'=>'', 'label'=>'Content Unit', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'],
                    'description'=>'Item content Unit for conversion', 'placeholder'=>'' ], 
                    ( isset( $datas['_parent_unit'] ) && $datas['_parent_unit'] != '' )? $datas['_parent_unit'] : 1, $view 
                ); 
            ?>
            <p class="description">Eg. '24' BOT of 500ml water</p>
            <p class="description">Eg. '300' G per PAC</p>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[inconsistent_unit]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Inconsistent Content Metric (kg/l)', 'required'=>false, 'attrs'=>[],
                    'description'=>'Item sell in metric kg/l might be inconsistent' ], 
                $datas['inconsistent_unit'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group flex-row flex-align-center">
        <?php 
            /*wcwh_form_field( $prefixName.'[kg_stock]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Handle Stock by Metric (kg/l)', 'required'=>false, 'attrs'=>[],
                    'description'=>'Item Stock in out by metric In kg/l' ], 
                $datas['kg_stock'], $view 
            );*/ 
        ?>
        </div>
    </div>
</div>

<div class='form-rows-group'>
    <h5>Functional <sup class="toolTip" title="Configurations for system functional"> ? </sup></h5>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item_group', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[grp_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Item Group', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['grp_id'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                if( $args['setting']['general']['use_item_storing_type'] ):

                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_store_type', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[store_type_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Item Storing Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options,  'description'=>'Type of storing environment'
                    ], 
                    $datas['store_type_id'], $view 
                ); 
                endif;
            ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'term_id', [ 'slug', 'name' ] );
                
                wcwh_form_field( $prefixName.'[category]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Item Category', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['category'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                $item_options = options_data( apply_filters( 'wcwh_get_item', [ 'not_id'=>$datas['id'], 'status'=>1 ], [], false, [ 'uom'=>1 ] ), 'id', [  'code', 'uom_code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[ref_prdt]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Refer Item With Same Nature', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $item_options, 'description'=>'Refer main item for duplication item with different sku'
                    ], 
                    $datas['ref_prdt'], $view 
                );
            ?>
        </div>
    </div>
    <div class="form-row">
        <!--<div class="col form-group">
            <?php 
            /*    $options = options_data( apply_filters( 'wcwh_get_stockout', [], [], false, [] ), 'id', [ 'name', 'order_type' ], '' );
                
                wcwh_form_field( $prefixName.'[_stock_out_type]', 
                    [ 'id'=>'_stock_out_type', 'type'=>'select', 'label'=>'Stockout Type', 'required'=>true, 'attrs'=>[],
                        'options'=> $options, 'description'=>'Inventory deduction method'
                    ], 
                    ( $datas['_stock_out_type'] )? $datas['_stock_out_type'] : 2, $view 
                ); */
            ?>
        </div>-->
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_weight_scale_key]', 
                [ 'id'=>'', 'label'=>'Scale Key', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 'description'=>'Shortcut key used on weight scale' ], 
                $datas['_weight_scale_key'], $view 
            ); 
        ?>
        </div>
		<div class="col form-group">
            <?php 
                $options = [ 'yes' => 'Sellable', 'no' => "Not For Sale", 'force' => 'Force Sellable' ];
                
                wcwh_form_field( $prefixName.'[_sellable]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'POS Sellable', 'required'=>false, 'attrs'=>[],
                        'options'=> $options, 'description'=>'Is Item Sellable On POS'
                    ], 
                    $datas['_sellable'], $view 
                ); 
				
				wcwh_form_field( $prefixName.'[_stock_out_type]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>true, 'attrs'=>[] ], 
                    2, $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $filter = [ 'status'=>1 ];
            if( $args['wh_code'] ) $filter['wh_code'] = $args['wh_code'];
            $options = options_data( apply_filters( 'wcwh_get_order_type', $filter, [], false, [] ), 'id', [ 'code', 'name', 'lead_time', 'order_period' ] );

            wcwh_form_field( $prefixName.'[reorder_type]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Order Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options, 
                ], 
                $datas['reorder_type'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[virtual]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Is Virtual Item', 'required'=>false, 'attrs'=>[] ], 
                $datas['virtual'], $view 
            ); 
        ?>
        </div>
    </div>
<?php if( current_user_cans( ['wh_admin_support'] ) ): ?>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[tolerance]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'Tolerance Percentage', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['tolerance'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[tolerance_rounding]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Tolerance Rounding', 'required'=>false, 'attrs'=>[], 'class'=>[],
                'options'=> [ 'default'=>'Default', 'ceil'=>'Round Up', 'floor'=>'Round Down' ] ], 
                $datas['tolerance_rounding'], $view 
            ); 
        ?>
        </div>
    </div>
<?php endif; ?>
</div>

<div class='form-rows-group'>
    <h5>Replace / Returnable Item</h5>
    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[is_returnable]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Is Special Item', 'required'=>false, 'attrs'=>[] ], 
                $datas['is_returnable'], $view 
            ); 
        ?>
        
        </div>
       
        <div class="col form-group">
            <?php  
                wcwh_form_field( $prefixName.'[returnable_item]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Returnable Item on Sale', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $item_options, 'description'=>'Returnable Item on Sale Eg, Empty Gas Tong'
                    ], 
                    $datas['returnable_item'], $view 
                );
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[calc_egt]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Calculate Returned Item', 'required'=>false, 'attrs'=>[] ], 
                $datas['calc_egt'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[add_gt_total]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Receive as Addition', 'required'=>false, 'attrs'=>[] ], 
                $datas['add_gt_total'], $view 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
            <?php  
                $opts = [ ''=>'No Auto Replace', 'yes'=>'Auto Replace' ];
                wcwh_form_field( $prefixName.'[auto_replacing]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Auto Replace on GR', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $opts, 'description'=>'Returnable Item Auto replacement on Goods Receipt'
                    ], 
                    $datas['auto_replacing'], $view 
                );
            ?>
        </div>
    </div>
</div>

<div class='form-rows-group'>
    <h5>Dimension</h5>
    <div class="form-row">
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_length]', 
                [ 'id'=>'', 'label'=>'Length / Long (cm)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_length'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_height]', 
                [ 'id'=>'', 'label'=>'Height / Tall (cm)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_height'], $view 
            );
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_width]', 
                [ 'id'=>'', 'label'=>'Width / Depth (cm)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_width'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_thickness]', 
                [ 'id'=>'', 'label'=>'Thickness (mm)', 'required'=>false, 'attrs'=>[] ], 
                $datas['_thickness'], $view 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_weight]', 
                [ 'id'=>'', 'label'=>'Weight (g)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_weight'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_volume]', 
                [ 'id'=>'', 'label'=>'Volume (ml)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_volume'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_capacity]', 
                [ 'id'=>'', 'label'=>'Capacity', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_capacity'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group"></div>
    </div>
</div>

<div class='form-rows-group'>
    <h5>Information</h5>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            $filter = [];
            if( $args['seller'] ) $filter['seller'] = $args['seller'];
            $options = options_data( apply_filters( 'wcwh_get_brand', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                
            wcwh_form_field( $prefixName.'[_brand]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Brand', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['_brand'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[_material]', 
                [ 'id'=>'', 'label'=>'Material', 'required'=>false, 'attrs'=>[] ], 
                $datas['_material'], $view 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[_model]', 
                [ 'id'=>'', 'label'=>'Model Info', 'required'=>false, 'attrs'=>[] ], 
                $datas['_model'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                $options = [ '' => 'None', 'yes' => "Halal", 'no' => "Non Halal" ];
                
                wcwh_form_field( $prefixName.'[_halal ]', 
                    [ 'id'=>' ', 'type'=>'select', 'label'=>'Halal', 'required'=>false, 'attrs'=>[],
                        'options'=> $options
                    ], 
                    $datas['_halal '], $view 
                ); 
            ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = options_data( WCWH_Function::get_countries() );
             wcwh_form_field( $prefixName.'[_origin_country]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Origin Country', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['_origin_country'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[_website]', 
                [ 'id'=>'', 'label'=>'Reference Website', 'required'=>false, 'attrs'=>[] ], 
                $datas['_website'], $view 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[spec]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Spec', 'required'=>false, 'attrs'=>[] ], 
                    $datas['spec'], $view 
                );
            ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[desc]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Description', 'required'=>false, 'attrs'=>[] ], 
                    $datas['desc'], $view 
                );
            ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
            <label for="" class=" control-label">Set Item Image</label>
            <?php
                if($datas['_thumbnail_id'])
                {
                    $attch = wp_get_attachment_image_src( $datas['_thumbnail_id'], 'full' );
                    if(empty($attch))
                    {
                        $src = WooCommerce::plugin_url().'/assets/images/placeholder.png';
                    }
                    else
                    {
                        $src = $attch[0];
                    }
                }
                else
                    $src = WooCommerce::plugin_url().'/assets/images/placeholder.png'; 
            ?>
            <p class="description">Click on the image to set item image <a id="remove_prdt_image" class="button displaynone">x</a></p>
            <?php if( !$args['view'] ): ?>
                <img id="prdt_image" class="prdt_image_class" src="<?php echo $src; ?>" style="max-width:256px;cursor:pointer;border:1px solid #dee2e6" />
            <?php else: ?>
                <img id="prdt_image" src="<?php echo $src; ?>" style="max-width:124px;" />
            <?php endif; ?>
            <input type="hidden" name="pos_prdt[_thumbnail_id]" id="upload_image_id" value="<?php echo ( $id )? $datas['_thumbnail_id'][0] : $_GET['img']; ?>" />
            <input type="hidden" id="default_img" class="default_img" value="<?php echo WooCommerce::plugin_url().'/assets/images/placeholder.png';?>" />

            <?php
                wcwh_form_field( $prefixName.'[_thumbnail_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'Description', 'required'=>false, 'attrs'=>[], 'class'=>['upload_image']], 
                    $datas['_thumbnail_id'], $view 
                );
            ?>
        </div>
    </div>
</div>

    <?php if( $datas['id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        var def_img = $('img.prdt_image_class').attr('src');
        window.send_to_editor_default = window.send_to_editor;
        $('.prdt_image_class').click(function(){
            // replace the default send_to_editor handler function with our own
            window.send_to_editor = window.attach_image;
            tb_show('', 'media-upload.php?type=image&amp;TB_iframe=true');

            return false;
        });
        
        $('#remove_prdt_image').click(function(){
            var d = $('input.default_img').val(); 
            $('img.prdt_image_class').attr('src', d );
            $('.upload_image').val(0);
        });

        window.attach_image = function(html) {
            // turn the returned image html into a hidden image element so we can easily pull the relevant attributes we need
            $('body').append('<div id="temp_image">' + html + '</div>');

            var img = $('#temp_image').find('img');

            imgurl   = img.attr('src');
            imgclass = img.attr('class');
            imgid    = parseInt(imgclass.replace(/\D/g, ''), 10);

            $('#upload_image_id').val(imgid);
            $('#remove_prdt_image').removeClass('displaynone');

            $('img.prdt_image_class').attr('src', imgurl);
            try{tb_remove();}catch(e){};
            $('#temp_image').remove();

            // restore the send_to_editor handler function
            window.send_to_editor = window.send_to_editor_default;
            
            $('input.upload_image').val($('input#upload_image_id').val());
        }
    });
</script>
<?php endif; ?>