<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="header-container">
        <h5>User Info</h5>
        
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[user_login]', 
                    [ 'id'=>'', 'label'=>'Username', 'required'=>true, 'attrs'=>[] ], 
                    $datas['user_login'], !empty( $datas['user_login'] )? true : $view
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $datas['role'] = maybe_unserialize( $datas['role'] );
                if( is_array( $datas['role'] ) ) $datas['role'] = array_keys( $datas['role'] );

                $options = $args['usable_roles'];
                $options[ 'norole' ] = "No Role / Deactivated";
                wcwh_form_field( $prefixName.'[role][]', 
                    [ 'id'=>'', 'label'=>'Role', 'type'=>'select', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=>$options, 'multiple'=>1 ], 
                   ( $datas['role'] )? $datas['role'] : 'norole', $view
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[user_email]', 
                    [ 'id'=>'', 'label'=>'Email', 'required'=>true, 'attrs'=>[] ], 
                    $datas['user_email'], $view
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[display_name]', 
                    [ 'id'=>'', 'label'=>'Name', 'required'=>true, 'attrs'=>[] ], 
                    !empty( $datas['first_name'] )? $datas['first_name'] : $datas['display_name'], $view
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[nickname]', 
                    [ 'id'=>'', 'label'=>'Nickname', 'required'=>false, 'attrs'=>[] ], 
                    $datas['nickname'], $view
                ); 
            ?>
            </div>
        </div>
    </div>

    <div class="header-container">
        <h5>Security</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[new_password]', 
                    [ 'id'=>'', 'label'=>'New Password', 'type'=>'password', 'required'=>( $datas['id'] )? false : true, 'attrs'=>[] ], 
                    '', $view
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['id'] > 0 )
                wcwh_form_field( $prefixName.'[confirm_password]', 
                    [ 'id'=>'', 'label'=>'Confirm Password', 'type'=>'password', 'required'=>false, 'attrs'=>[] ], 
                    '', $view
                ); 
            ?>
            </div>
        </div>
    <?php if( current_user_cans( [ 'wh_super_admin' ] ) ): ?>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $view ) $hashed = $datas['user_pass'];
                wcwh_form_field( $prefixName.'[password_hash]', 
                    [ 'id'=>'', 'label'=>'Encrypted Password', 'type'=>'text', 'required'=>false, 'attrs'=>[] ], 
                    $hashed, $view
                ); 
            ?>
            </div>
        </div>
    <?php endif; ?>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[start_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective From', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker', 'removable'] ], 
                    $datas['start_date'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[end_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective To', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker', 'removable'] ], 
                    $datas['end_date'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <?php if( is_plugin_active( 'woocommerce-point-of-sale/woocommerce-point-of-sale.php' ) ): ?>
    <div class="header-container">
        <h5>Point of Sale (POS)</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = WC_POS()->outlet()->get_data_names();
                
                wcwh_form_field( $prefixName.'[outlet][]', 
                    [ 'id'=>'', 'label'=>'Outlet', 'type'=>'select', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                        'options'=>$options, 'multiple'=>1 ], 
                    maybe_unserialize( $datas['outlet'] ), $view
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = ['disable'=>'Disable', 'enable'=>'Enable'];
                
                wcwh_form_field( $prefixName.'[discount]', 
                    [ 'id'=>'', 'label'=>'Discount', 'type'=>'select', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=>$options ], 
                    $datas['discount'], $view
                ); 
            ?>
            </div>
            <div class="col form-group">
            
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