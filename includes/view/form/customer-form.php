<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_customer';
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
                [ 'id'=>'', 'label'=>'Customer Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'].( ( $view && !$datas['status'] )? " ({$datas['status_name']})" : "" ), $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
    	<div class="col form-group">
        <?php 
            $uid = ( $datas['uid'] )? $datas['uid'] : '012345';
            $full_uid = ( $datas['full_uid'] )? $datas['full_uid'] : '088012345';
            wcwh_form_field( $prefixName.'[uid]', 
                [ 'id'=>'', 'label'=>'SAP Employee No.', 'required'=>true, 'attrs'=>[], 'placeholder'=>$uid, 'description'=>'Employee ID '.$uid.' (6 digit & below) OR Full ID '.$full_uid.' (9 digit)' ], 
                $datas['uid'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            $min_date = date( 'm/d/Y', strtotime( date( 'Y-m-d' ) ) );
            $min_date = 'data-dd-min-date="'.$min_date.'"';
            if( $datas['last_day'] ) $last_day = date( 'm/d/Y', strtotime( $datas['last_day'] ) );
            wcwh_form_field( $prefixName.'[last_day]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'Last Day Date', 'required'=>false, 
                    'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$last_day.'"', $min_date ], 
                    'class'=>['doc_date', 'picker', 'removable'] ], 
                ( $datas['last_day'] )? date( 'Y-m-d', strtotime( $datas['last_day'] ) ) : "", $view 
            ); 
        ?>
        </div>
    </div>

    <?php if( $args['view'] ): ?>
    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[prev_sapuid]', 
                [ 'id'=>'', 'label'=>'Old Employee No.', 'required'=>false, 'attrs'=>[] ], 
                $datas['prev_sapuid'], $view 
            ); 
        ?>
        </div>
     
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[prev_sapuid_date]', 
                [ 'id'=>'', 'label'=>'Old No. Date', 'required'=>false, 'attrs'=>[] ], 
                $datas['prev_sapuid_date'], $view 
            ); 
        ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Customer Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], ( current_user_cans( [ 'wh_admin_support' ] ) )? $view : true 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[serial]', 
                [ 'id'=>'', 'label'=>'Barcode', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'], 'description'=>'System generate' ], 
                $datas['serial'], true 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [ 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_account_type', $filter, [], false, [] ), 'id', [ 'code', 'employee_prefix' ] );
                
                wcwh_form_field( $prefixName.'[acc_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Account Type', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['acc_type'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group flex-row flex-align-center">
            <?php
                wcwh_form_field( $prefixName.'[usage_type]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'RMS USE', 'required'=>false, 'attrs'=>[],
                        'description'=>'Customer Limit for Remittance Money Service Only' ], 
                    $datas['usage_type'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_origin_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[origin]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Origin', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['origin'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer_job', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[cjob_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Job / Position', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['cjob_id'], $view 
                ); 
            ?>
            <?php 
                /*$filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer', $filter ), 'id', [ 'code', 'uid', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Superior', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                );*/ 
            ?>
        </div>
    </div>

    <div class="form-row">
        <?php if( current_user_cans( [ 'save_wh_credit', 'assign_group_wh_customer' ] ) ): ?>
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                $opt_val = $args['setting'][ $args['section'] ]['default_credit_group'];

                wcwh_form_field( $prefixName.'[cgroup_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Credit Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    ( $datas['cgroup_id'] )? $datas['cgroup_id'] : ( $opt_val? $opt_val : '' ), $view 
                ); 
            ?>
        </div>
        <?php else: ?>
        
            <?php 
                //$opt_val = $args['setting'][ $args['section'] ]['default_credit_group'];

                wcwh_form_field( $prefixName.'[cgroup_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    ( $datas['cgroup_id'] )? $datas['cgroup_id'] : '', $view 
                ); 
            ?>
            
        <?php endif; ?>
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_company', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[comp_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Company', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['comp_id'], $view 
                ); 
            ?>
        </div>
    </div>

    <?php if( $args['select_warehouse'] ): ?>
        <div class="form-row">
            <div class="col form-group">
                <?php 
                    $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                    $options = options_data( apply_filters( 'wcwh_get_warehouse', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
                    wcwh_form_field( $prefixName.'[wh_code]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Warehouse Code', 'required'=>true, 'attrs'=>[],
                            'options'=> $options
                        ], 
                        $datas['wh_code'], $view 
                    ); 
                ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[email]', 
                [ 'id'=>'', 'label'=>'Email', 'required'=>false, 'attrs'=>[] ], 
                $datas['email'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[phone_no]', 
                [ 'id'=>'', 'label'=>'Phone No.', 'required'=>false, 'attrs'=>[] ], 
                $datas['phone_no'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'attachments', 
                    [ 'id'=>'', 'type'=>'file', 'label'=>'Photo', 'required'=>false, 'attrs'=>['accept = image/*'] ], 
                    '', $view 
                ); 

                if( $args['attachment'] )
                {
                    ?>
                    <table class="wp-list-table widefat striped">
                    <?php
                    foreach( $args['attachment'] as $i => $attach )
                    {
                        $attach['i'] = $i;
                        $attach['view'] = $view;
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/attachments-row.php', $attach );

                        echo $tpl = str_replace( $find, $replace, $tpl );

                        echo "<br>";
                    }
                    ?>
                    </table>
                    <?php
                }
            ?>
            </div>
        </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[ibs]', 
                [ 'id'=>'', 'label'=>'No.ID/IBS', 'required'=>false, 'attrs'=>[] ], 
                $datas['ibs'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[passport]', 
                [ 'id'=>'', 'label'=>'Passport Number', 'required'=>false, 'attrs'=>[] ], 
                $datas['passport'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[phase]', 
                [ 'id'=>'', 'label'=>'Phase', 'required'=>false, 'attrs'=>[] ], 
                $datas['phase'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            // wcwh_form_field( $prefixName.'[ibs]', 
            //     [ 'id'=>'', 'label'=>'IBS/FAS Number', 'required'=>false, 'attrs'=>[] ], 
            //     $datas['IBS'], $view 
            // ); 
        ?>
        </div>
    </div>


    <?php
        if( $args['credit_info'] && $view ):
            $info = $args['credit_info'];
    ?>
<div class='form-rows-group'>
    <h5>Credit Info</h5>
    <div class="form-row">
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[cfrom_date]', 
                [ 'id'=>'', 'label'=>'Credit From Date', 'required'=>false, 'attrs'=>[] ], 
                $info['from_date'], true 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[cto_date]', 
                [ 'id'=>'', 'label'=>'Credit To Date', 'required'=>false, 'attrs'=>[] ], 
                $info['to_date'], true 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[cassigned_credit]', 
                [ 'id'=>'', 'label'=>'Assigned Credit', 'required'=>false, 'attrs'=>[] ], 
                round_to( $info['assigned_credit'], 2, 1, 1 ), true 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[ctopup_credit]', 
                [ 'id'=>'', 'label'=>'Topup', 'required'=>false, 'attrs'=>[] ], 
                round_to( $info['topup_credit'], 2, 1, 1 ), true 
            ); 
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[ctotal_creditable]', 
                [ 'id'=>'', 'label'=>'Total Creditable', 'required'=>false, 'attrs'=>[] ], 
                round_to( $info['total_creditable'], 2, 1, 1 ), true 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[cused_credit]', 
                [ 'id'=>'', 'label'=>'Used Credit', 'required'=>false, 'attrs'=>[] ], 
                round_to( $info['used_credit'], 2, 1, 1 ), true 
            );
        ?>
        </div>
    </div>
    <div class="form-row">
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[cusable_credit]', 
                [ 'id'=>'', 'label'=>'Usable Credit', 'required'=>false, 'attrs'=>[] ], 
                round_to( $info['usable_credit'], 2, 1, 1 ), true 
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