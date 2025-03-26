<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_stockout';
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
                [ 'id'=>'', 'label'=>'Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[ref_id]', 
                [ 'id'=>'', 'label'=>'Ref ID', 'required'=>true, 'attrs'=>[] ], 
                $datas['ref_id'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[order_type]', 
                [ 'id'=>'', 'label'=>'Order Type', 'required'=>true, 'attrs'=>[] ], 
                $datas['order_type'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[ordering]', 
                [ 'id'=>'', 'label'=>'Ordering', 'required'=>true, 'attrs'=>[], 'class'=>[] ], 
                $datas['ordering'], $view 
            );
            ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[priority]', 
                [ 'id'=>'', 'type'=>'number', 'label'=>'Priority', 'required'=>true, 'attrs'=>['min="0"'], 'class'=>['numonly'] ], 
                $datas['priority'], $view 
            );
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[default_value]', 
                [ 'id'=>'', 'label'=>'Default Value', 'required'=>false, 'attrs'=>[] ], 
                $datas['default_value'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>