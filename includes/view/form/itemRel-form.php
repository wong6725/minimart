<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_item_rel';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $filter = [ 'id'=>$datas['id'], 'status'=>1 ];
            if( $args['seller'] ) $filter['seller'] = $args['seller'];
            $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1 ] ), 'id', [ 'code', 'uom_code', 'name' ] );
            
            wcwh_form_field( $prefixName.'[items_id]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Item', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options, 
                ], 
                $datas['items_id'], $view 
            ); 

            wcwh_form_field( $prefixName.'[wh_id]', 
                [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                !empty( $datas['wh_id'] )? $datas['wh_id'] : $args['wh_code'], $view 
            );

            wcwh_form_field( $prefixName.'[rel_type]', 
                [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                !empty( $datas['rel_type'] )? $datas['rel_type'] : 'reorder_type', $view 
            );
        ?>
        </div>
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
    </div>

    <?php if( $datas['id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>