<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_brand';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[name]', 
                [ 'id'=>'', 'label'=>'Brand Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
    	<div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Brand Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[brand_no]', 
                    [ 'id'=>'', 'label'=>'SAP Brand No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['brand_no'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_brand', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
        </div>
         <div class="col form-group">
            <?php 
                /*$options = options_data( apply_filters( 'wcwh_get_company', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[comp_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Company', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['comp_id'], $view 
                ); */
            ?>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>