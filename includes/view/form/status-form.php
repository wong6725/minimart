<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_status';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[title]', 
                [ 'id'=>'', 'label'=>'Status Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['title'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[key]', 
                [ 'id'=>'', 'label'=>'Status Key', 'required'=>true, 'attrs'=>[] ], 
                $datas['key'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[status]', 
                [ 'id'=>'', 'type'=>'number', 'label'=>'Status Numeric', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                ( $datas['status'] )? $datas['status'] : 0, $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[type]', 
                [ 'id'=>'', 'label'=>'Type', 'required'=>true, 'attrs'=>[] ], 
                ( $datas['type'] )? $datas['type'] : 'default', $view 
            );
            ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[order]', 
                [ 'id'=>'', 'type'=>'number', 'label'=>'Ordering', 'required'=>true, 'attrs'=>['min="0"'], 'class'=>['numonly'] ], 
                ( $datas['order'] )? $datas['order'] : 0, $view 
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