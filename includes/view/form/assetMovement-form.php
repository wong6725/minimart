<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_movement';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="header-container">
        <h5>Header</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[code]', 
                    [ 'id'=>'', 'label'=>'Movement Code', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'] ], 
                    $datas['code'], true 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[asset_no]', 
                    [ 'id'=>'', 'label'=>'Asset No.', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'] ], 
                    $datas['asset_no'], true 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[location_code]', 
                    [ 'id'=>'', 'label'=>'Client Code', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'] ], 
                    $datas['location_code'], true 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[post_date]', 
                    [ 'id'=>'', 'label'=>'Posting Date', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'] ], 
                    $datas['post_date'], true 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[end_date]', 
                    [ 'id'=>'', 'label'=>'Returned Date', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'] ], 
                    $datas['end_date'], true 
                ); 
            ?>
            </div>
        </div>
    
    </div>

    <div class="detail-container">
        <h5>Details</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                echo $args['render'];
            ?>
            </div>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>