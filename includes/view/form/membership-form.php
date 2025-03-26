<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_member';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

<div class='form-rows-group'>
    <h5>Member Info</h5>
    <div class="form-row">
        <div class="col form-group">
            <?php if( $datas['doc_id'] ): ?>
                <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
            <?php endif; ?>

            <?php
                wcwh_form_field( $prefixName.'[member]', 
                    [ 'id'=>'', 'label'=>'Member / Customer', 'required'=>true, 'attrs'=>[] ], 
                    $datas['member'], 1  
                );

                wcwh_form_field( $prefixName.'[customer_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['customer_id'], $view 
                );
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[phone_no]', 
                [ 'id'=>'', 'label'=>'Phone No.', 'required'=>true, 'attrs'=>[] ], 
                $datas['phone_no'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[email]', 
                [ 'id'=>'', 'label'=>'Email', 'required'=>false, 'attrs'=>[] ], 
                $datas['email'], $view 
            ); 
        ?>
        </div>
    </div>

    <?php if( current_user_cans( [ 'wh_admin_support' ] ) ): ?>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[serial]', 
                    [ 'id'=>'', 'label'=>'Serial / QR No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['serial'], 1 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[pin]', 
                    [ 'id'=>'', 'label'=>'Hashed', 'required'=>false, 'attrs'=>[] ], 
                    $datas['pin'], 1 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[total_debit]', 
                    [ 'id'=>'', 'label'=>'Total Debit', 'required'=>false, 'attrs'=>[] ], 
                    $datas['total_debit'], 1 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[total_used]', 
                    [ 'id'=>'', 'label'=>'Total Spend', 'required'=>false, 'attrs'=>[] ], 
                    $datas['total_used'], 1 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[balance]', 
                    [ 'id'=>'', 'label'=>'Usable Balance', 'required'=>false, 'attrs'=>[] ], 
                    $datas['balance'], 1 
                ); 
            ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if( ! $view ): ?>
<div class='form-rows-group'>
    <h5><?php echo ( $datas['id'] > 0 )? 'Renew Pin <sup>Leave this section blank if no changes</sup>' : 'Security' ?></h5>
    <div class="form-row">
        <?php if( $datas['id'] > 0 ): ?>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[old_pin]', 
                    [ 'id'=>'', 'label'=>'Old Pin', 'type'=>'password', 'required'=>false, 'attrs'=>['maxlength="6"'], 
                        'class'=>[ 'numonly', 'positive-integer' ], 'placeholder'=>'Previous Pin' ], 
                    '', $view
                ); 
            ?>
            </div>
        <?php endif; ?>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[new_pin]', 
                [ 'id'=>'', 'label'=>'New Pin', 'type'=>'password', 'required'=>( $datas['id'] )? false : true, 'attrs'=>['maxlength="6"'],
                    'class'=>[ 'numonly', 'positive-integer' ], 'placeholder'=>'6 Digit Number Only' ], 
                '', $view
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[confirm_pin]', 
                [ 'id'=>'', 'label'=>'Confirm Pin', 'type'=>'password', 'required'=>( $datas['id'] )? false : true, 'attrs'=>['maxlength="6"'], 
                    'class'=>[ 'numonly', 'positive-integer' ], 'placeholder'=>'Repeat Pin' ], 
                '', $view
            ); 
        ?>
        </div>
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