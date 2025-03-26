<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_payment_term';
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
                [ 'id'=>'', 'label'=>'Term Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Code', 'required'=>true, 'attrs'=>[] ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = [ 'none'=>'None', 'days'=>'Days', 'month'=>'Month', 'date'=>'Date' ];
            wcwh_form_field( $prefixName.'[type]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Term Type', 'required'=>false, 'attrs'=>[],
                    'options'=> $options
                ], 
                $datas['type'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[days]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'Day(s) / Month(s) / Date', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                ( $datas['days'] )? $datas['days'] : 0, $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[creditability]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Creditable', 'required'=>false, 'attrs'=>[] ], 
                $datas['creditability'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[penalty_factor]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'Late Payment Interest', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'],
                    'description'=>'Late payment interest will be imposed after due date onwards per annum on a daily basis' ], 
                $datas['penalty_factor'], $view 
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