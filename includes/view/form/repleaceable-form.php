<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_repleaceable';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[total_prev]', 
                [ 'id'=>'', 'label'=>'Previous Total', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['total_prev'], 1 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[total_user]', 
                [ 'id'=>'', 'label'=>'Last User Input Total', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['total_user'], 1 
            ); 
        ?>
        </div>
    </div>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[total]', 
                [ 'id'=>'', 'label'=>'Total', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['total'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>