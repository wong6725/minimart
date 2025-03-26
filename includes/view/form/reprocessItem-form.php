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
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[required_item]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Material', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>"Material needed to produce End Product"
                    ], 
                    $datas['required_item'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                //$options = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'uom'=>1, 'usage'=>1 ] ), 'id', [ 'code', 'uom_code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[items_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'End Product / Outcome', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options, 'description'=>"Outcome item of reprocess procedure"
                    ], 
                    $datas['items_id'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[required_qty]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Material Qty Needed', 'required'=>false, 'attrs'=>[], 'class'=>[],
                        'description'=>"Material amount needed to process End Product"
                    ], 
                    $datas['required_qty'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
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