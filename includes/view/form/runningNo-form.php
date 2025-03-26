<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_running';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[doc_type]', 
                [ 'id'=>'', 'label'=>'Doc Type', 'required'=>true, 'attrs'=>[], 'class'=>[] ], 
                $datas['doc_type'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[ref_type]', 
                [ 'id'=>'', 'label'=>'Ref Type', 'required'=>true, 'attrs'=>[], 'class'=>[] ], 
                ( $datas['ref_type'] )? $datas['ref_type'] : 'default', $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[ref_id]', 
                [ 'id'=>'', 'label'=>'Ref ID', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['ref_id'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            $options = [ 'default'=>'Default', 'random'=>'Random Number', 'range'=>'Range Number' ];
            wcwh_form_field( $prefixName.'[type]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Generate Type', 'required'=>false, 'attrs'=>[], 'class'=>[],
                    'options'=> $options ], 
                $datas['type'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[length]', 
                [ 'id'=>'', 'label'=>'Length', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['length'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[prefix]', 
                [ 'id'=>'', 'label'=>'Prefix / Min', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $datas['prefix'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[suffix]', 
                [ 'id'=>'', 'label'=>'Suffix / Max', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $datas['suffix'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[next_no]', 
                [ 'id'=>'', 'label'=>'Next No.', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $datas['next_no'], $view 
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