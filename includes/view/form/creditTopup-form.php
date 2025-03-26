<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_credit_topup';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $not_acc_type = $args['setting']['wh_customer']['non_editable_by_acc_type'];
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                if( $not_acc_type ) $filter['not_acc_type'] = $not_acc_type;
                $options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'uid', 'name' ] );
                
                wcwh_form_field( $prefixName.'[customer_code]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Customer', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['customer_code'], $view 
                ); 
            ?>
        </div>
    </div>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[credit_limit]', 
                [ 'id'=>'', 'label'=>'Credit Limit', 'required'=>true, 'attrs'=>[ 'min="-999"', 'max="999"' ], 'class'=>['numonly','minmaxnum'] ], 
                $datas['credit_limit'], $view 
            ); 
			wcwh_form_field( $prefixName.'[percentage]', 
                [ 'id'=>'percentage', 'type'=>'hidden', 'attrs'=>[], 'class'=>[''] ], 
                '100', $view 
            );
        ?>
        </div>
		<div class="col form-group">
        <?php 
            if( $datas['effective_from'] ) $date = date( 'm/d/Y', strtotime( $datas['effective_from'] ) );
            else $datas['effective_from'] = $date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );
            if( !empty( $datas['now_from'] ) )
            {
                $min_date = date( 'm/d/Y', strtotime( $datas['now_from'] ) );
                $min_date = 'data-dd-min-date="'.$min_date.'"';
            }

            wcwh_form_field( $prefixName.'[effective_from]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'Effective From', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$date.'"', $min_date ], 'class'=>['doc_date', 'picker'] ], 
                ( $datas['effective_from'] )? date( 'Y-m-d', strtotime( $datas['effective_from'] ) ) : "", $view 
            ); 
        ?>
        </div>
        <!--<div class="col form-group">
        <?php 
            /*wcwh_form_field( $prefixName.'[percentage]', 
                [ 'id'=>'', 'label'=>'Usage Percantage', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                ( $datas['percentage'] )? $datas['percentage'] : '100', $view 
            );*/ 
        ?>
        </div>-->
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>