<?php
//Steven Create UOM Conversion Form based on UOM form
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_uom_conversion';
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
            $uoms = apply_filters( 'wcwh_get_uom', $filter, [], false, [] );
            $options = options_data( $uoms, 'code', [ 'code', 'name' ] );

            $uom_options = [];
            foreach( $options as $key => $title )
            {
                $uom_options[ strtoupper($key) ] = $title;
            }
            
            wcwh_form_field( $prefixName.'[from_uom]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'From UOM', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $uom_options
                ], 
                $datas['from_uom'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[from_unit]', 
                [ 'id'=>'', 'label'=>'From Unit', 'required'=>true, 'class'=>['numonly'], 'attrs'=>[] ], 
                $datas['from_unit'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php   
            wcwh_form_field( $prefixName.'[to_uom]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'To UOM', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $uom_options
                ], 
                $datas['to_uom'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[to_unit]', 
                [ 'id'=>'', 'label'=>'To Unit', 'required'=>true, 'class'=>['numonly'], 'attrs'=>[] ], 
                $datas['to_unit'], $view 
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