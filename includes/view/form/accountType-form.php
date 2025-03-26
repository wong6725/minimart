<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_acc_type';
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
                [ 'id'=>'', 'label'=>'Account Type Name', 'required'=>true, 'attrs'=>[], 'placeholder'=>'DVM188' ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Account Type Code', 'required'=>true, 'attrs'=>[] ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[employee_prefix]', 
                [ 'id'=>'', 'label'=>'Employee ID Prefix', 'required'=>false, 'attrs'=>[], 'placeholder'=>'088' ], 
                $datas['employee_prefix'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            $filter = [];
            if( $args['seller'] ) $filter['seller'] = $args['seller'];
            $options = options_data( apply_filters( 'wcwh_get_customer_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );

            wcwh_form_field( $prefixName.'[def_cgroup_id]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Default Credit Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                ( $datas['def_cgroup_id'] )? $datas['def_cgroup_id'] : ( $opt_val? $opt_val : '' ), $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $filter = [ 'is_base'=>1 ];
            if( $args['seller'] ) $filter['seller'] = $args['seller'];
            $options = options_data( apply_filters( 'wcwh_get_credit_term', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'name', 'days' ] );
            
            wcwh_form_field( $prefixName.'[term_id]', 
                [ 'id'=>' ', 'type'=>'select', 'label'=>'Credit Term (Higher privilege)', 'required'=>false, 'attrs'=>[],
                    'options'=> $options
                ], 
                $datas['term_id'], $view 
            ); 
        ?>
        </div>
    </div>

<?php if( current_user_cans( [ 'wh_admin_support' ] ) ): ?>
    <div class='form-rows-group'>
        <h5>Tools Request Related</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[wage_type]', 
                    [ 'id'=>'', 'label'=>'Groceries Wage Type', 'required'=>false, 'attrs'=>[] ], 
                    $datas['wage_type'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[tool_wage]', 
                    [ 'id'=>'', 'label'=>'Tool Wage Type', 'required'=>false, 'attrs'=>[] ], 
                    $datas['tool_wage'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[eq_wage]', 
                    [ 'id'=>'', 'label'=>'Equipment Wage Type', 'required'=>false, 'attrs'=>[] ], 
                    $datas['eq_wage'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if( current_user_cans( [ 'wh_admin_support' ] ) ): ?>
    <div class='form-rows-group'>
        <h5>SAP Integrated Credit Topup</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[plant]', 
                    [ 'id'=>'', 'label'=>'SAP Plant', 'required'=>false, 'attrs'=>[] ], 
                    $datas['plant'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[sv]', 
                    [ 'id'=>'', 'label'=>'SAP Server (PRD:0, DVM:1)', 'required'=>false, 'attrs'=>[], 'placeholder'=>'PRD:0, DVM:1' ], 
                    $datas['sv'], $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[topup_time]', 
                    [ 'id'=>'', 'label'=>'Auto Topup Time', 'required'=>false, 'attrs'=>[], 'placeholder'=>'H:i:s (01:00:00)' ], 
                    $datas['topup_time'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[auto_topup]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Apply Auto Credit Topup', 'required'=>false, 'attrs'=>[] ], 
                    $datas['auto_topup'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>
<?php endif; ?>

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
