<?php
declare(strict_types=1);

if (!defined("ABSPATH")) exit;

$datas = $args['data'] ?? [];
$view = $args['view'] ?? false;
$prefixName = '_' . ($args['prefixName'] ?? 'asset');

// Initialize required array keys
$args['tplName'] ??= '';
$args['new'] ??= '';
$args['token'] ??= '';
$args['hook'] ??= '';

// Initialize datas array keys
$datas['name'] ??= '';
$datas['chinese_name'] ??= '';
$datas['code'] ??= '';
$datas['serial'] ??= '';

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
            wcwh_form_field(
                name: $prefixName.'[name]',
                args: [
                    'id' => '',
                    'label' => 'Asset Name', 
                    'required' => true,
                    'attrs' => []
                ],
                value: $datas['name'],
                view: $view
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[chinese_name]', 
                [ 'id'=>'', 'label'=>'Chinese Name', 'required'=>false, 'attrs'=>[] ], 
                $datas['chinese_name'], $view 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[serial]', 
                [ 'id'=>'', 'label'=>'Barcode', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'], 'description'=>'System generate' ], 
                $datas['serial'], true 
            ); 
        ?>
        </div>
    </div>
</div>

<div class='form-rows-group'>
    <h5>Functional <sup class="toolTip" title="Configurations for system functional"> ? </sup></h5>
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $options = [ 'default' => 'Default', 'container' => 'Container' ];
                
                wcwh_form_field( $prefixName.'[type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Usage Type', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'], 
                        'options'=> $options
                    ], 
                    ( $datas['type'] )? $datas['type'] : 'container', $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_asset', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent Asset', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
        </div>
    </div>
</div>

<div class='form-rows-group'>
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
            wcwh_form_field( $prefixName.'[_weight]', 
                [ 'id'=>'', 'label'=>'Weight (kg)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_weight'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[_capacity]', 
                [ 'id'=>'', 'label'=>'Capacity (kg)', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['_capacity'], $view 
            ); 
        ?>
        </div>
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
                $terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
                $options = options_data( (array) $terms, 'term_id', [ 'slug', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[category]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Category', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'], 
                        'options'=> $options
                    ], 
                    $datas['category'], $view 
                ); 
            ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[_model]', 
                [ 'id'=>'', 'label'=>'Model info', 'required'=>false, 'attrs'=>[] ], 
                $datas['_model'], $view 
            ); 
        ?>
        </div>
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
    </div>
    <div class="form-row">
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
                wcwh_form_field( $prefixName.'[desc]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Description', 'required'=>false, 'attrs'=>[] ], 
                    $datas['desc'], $view 
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
<?php endif; ?>