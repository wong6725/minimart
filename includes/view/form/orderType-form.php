<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_order_type';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            $options = options_data( apply_filters( 'wcwh_get_warehouse', ['status'=>1 ], [], false, [] ), 'code', [ 'code', 'name' ], '' );
            
            if( !empty( $args['wh_code'] ) )
            {
                wcwh_form_field( $prefixName.'[wh_code]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Company / Outlet', 'required'=>false, 'attrs'=>[] ], 
                    $options[ $args['wh_code'] ], true 
                );  
                wcwh_form_field( $prefixName.'[wh_code]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                     $args['wh_code'], $view
                );  
            }
            else
            {
                wcwh_form_field( $prefixName.'[wh_code]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Company / Outlet', 'required'=>true, 'attrs'=>[], 'class'=>[ 'select2Strict' ],
                        'options'=> $options
                    ], 
                    ( $args['wh_code'] )? $args['wh_code'] : $datas['wh_code'], $view 
                ); 
            }
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[name]', 
                [ 'id'=>'', 'label'=>'Order Type Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Order Type Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[lead_time]', 
                [ 'id'=>'', 'label'=>'Lead Time (Day)', 'required'=>true, 'attrs'=>[], 'class'=>['numonly', 'positive-number'] ], 
                $datas['lead_time'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[order_period]', 
                [ 'id'=>'', 'label'=>'Order Period (Day)', 'required'=>true, 'attrs'=>[], 'class'=>['numonly', 'positive-number'] ], 
                $datas['order_period'], $view 
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

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>