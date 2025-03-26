<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_section';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[section_id]', 
                [ 'id'=>'', 'label'=>'Section ID', 'required'=>true, 'attrs'=>[] ], 
                $datas['section_id'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[table]', 
                [ 'id'=>'', 'label'=>'DB Table', 'required'=>true, 'attrs'=>[] ], 
                $datas['table'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[table_key]', 
                [ 'id'=>'', 'label'=>'DB Table Key', 'required'=>true, 'attrs'=>[] ], 
                $datas['table_key'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[desc]', 
                [ 'id'=>'', 'label'=>'Section Title', 'required'=>true, 'attrs'=>[] ], 
                $datas['desc'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[push_service]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Push Service', 'required'=>false, 'attrs'=>[] ], 
                $datas['push_service'], $view 
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