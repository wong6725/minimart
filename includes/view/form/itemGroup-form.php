<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_item_group';
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
                [ 'id'=>'', 'label'=>'Item Group Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
    	<div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Group Code', 'required'=>true, 'attrs'=>[] ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[prefix]', 
                [ 'id'=>'', 'label'=>'Prefix', 'required'=>false, 'attrs'=>[], 'description'=>'Prefix for Item Running No.' ], 
                $datas['prefix'], $view 
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