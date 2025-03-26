<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_credit';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <?php if( $args['scheme'] == "customer" ): ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'uid', 'name' ] );
                
                wcwh_form_field( $prefixName.'[ref_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Customer', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['ref_id'], $view 
                ); 

                wcwh_form_field( $prefixName.'[scheme]', 
                    [ 'id'=>'scheme', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
                    $args['scheme'], $view 
                ); 
            ?>
        </div>
    </div>

    <?php elseif( $args['scheme'] == "customer_group" ): ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[ref_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Customer Group', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['ref_id'], $view 
                ); 

                wcwh_form_field( $prefixName.'[scheme]', 
                    [ 'id'=>'scheme', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
                    $args['scheme'], $view 
                ); 
            ?>
        </div>
    </div>

    <?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [ 'is_base'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_credit_term', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'name', 'days' ] );
                
                wcwh_form_field( $prefixName.'[term_id]', 
                    [ 'id'=>' ', 'type'=>'select', 'label'=>'Credit Term', 'required'=>true, 'attrs'=>[],
                        'options'=> $options
                    ], 
                    $datas['term_id'], $view 
                ); 
            ?>
        </div>

        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[credit_limit]', 
                [ 'id'=>'', 'label'=>'Credit Limit', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['credit_limit'], $view 
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