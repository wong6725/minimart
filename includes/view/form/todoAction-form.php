<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_todo_action';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            $options = options_data( apply_filters( 'wcwh_get_arrangement', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'id', 'section', 'action_type' ] );
            
            wcwh_form_field( $prefixName.'[arr_id]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Arrangement ID', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['arr_id'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            $options = $args['permission'];
            
            wcwh_form_field( $prefixName.'[responsible]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Responsible', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['responsible'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = $args['actions'];
            
            wcwh_form_field( $prefixName.'[next_action]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Next Action', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['next_action'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            $options = $args['actions'];
            
            wcwh_form_field( $prefixName.'[trigger_action]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Trigger Action', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['trigger_action'], $view 
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