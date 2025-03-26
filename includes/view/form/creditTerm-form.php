<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_credit_term';
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
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = [];
            for( $i = 1; $i <= 31; $i++ ) $options[$i] = $i;
                
            wcwh_form_field( $prefixName.'[days]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Day of Month', 'required'=>true, 'attrs'=>[],
                    'options'=> $options
                ], 
                $datas['days'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[offset]', 
                [ 'id'=>'', 'type'=>'number', 'label'=>'Offset', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['offset'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'is_base'=>1, 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_credit_term', $filter, [], false, [] ), 'id', [ 'name', 'days' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent Term', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
        <?php  
             if( $datas['apply_date'] ) $date = date( 'm/d/Y', strtotime( $datas['apply_date'] ) );

            wcwh_form_field( $prefixName.'[apply_date]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'Apply Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m"', 'data-dd-hide-day="true"', 'data-dd-default-date="'.$date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                ( $datas['apply_date'] )? date( 'Y-m-d', strtotime( $datas['apply_date'] ) ) : "", $view 
            ); 
        ?>
        </div>
    </div>

<?php if( current_user_cans( [ 'wh_admin_support' ] ) ): ?>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[wage_type]', 
                [ 'id'=>'', 'label'=>'Wage Type', 'required'=>false, 'attrs'=>[] ], 
                $datas['wage_type'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        </div>
    </div>
<?php endif; ?>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>