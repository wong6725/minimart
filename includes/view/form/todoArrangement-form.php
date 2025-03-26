<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_arrangement';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_section', [], [], false, [] ), 'section_id', [ 'section_id', 'desc' ] );
                
                wcwh_form_field( $prefixName.'[section]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Section ID', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['section'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[id]', 
                [ 'id'=>'', 'label'=>'Arr ID', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'], 'description'=>'System generate' ], 
                $datas['id'], true 
            ); 
        ?>
        </div>
    </div>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[match_status]', 
                [ 'id'=>'', 'label'=>'Match Status', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['match_status'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[match_proceed]', 
                [ 'id'=>'', 'label'=>'Match Proceed', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['match_proceed'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[match_halt]', 
                [ 'id'=>'', 'label'=>'Match Halt', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['match_halt'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = $args['action_type'];
                
            wcwh_form_field( $prefixName.'[action_type]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Action Type', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                $datas['action_type'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[title]', 
                [ 'id'=>'', 'label'=>'Title', 'required'=>true, 'attrs'=>[], 'class'=>[] ], 
                $datas['title'], $view 
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