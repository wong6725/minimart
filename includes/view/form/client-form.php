<?php
declare(strict_types=1);

if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];
$def_country = ( $args['def_country'] )? $args['def_country'] : 'MY';

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_client';
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
                [ 'id'=>'', 'label'=>'Client Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
    	<div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Client Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[client_no]', 
                    [ 'id'=>'', 'label'=>'SAP Client No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['client_no'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[tin]', 
                    [ 'id'=>'', 'label'=>'Tin No.', 'required'=>false, 'attrs'=>[], 'description'=>'Tin No. For E-Invoice Usage' ], 
                    $datas['tin'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[sst_no]', 
                    [ 'id'=>'', 'label'=>'SST No.', 'required'=>false, 'attrs'=>[], 'description'=>'SST Registration No. For E-Invoice Usage If Applicable' ], 
                    $datas['sst_no'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $options = apply_filters( 'wcwh_get_i18n', 'id_types' );
                
                wcwh_form_field( $prefixName.'[id_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Business ID Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'], 
                        'options'=> $options
                    ], 
                    $datas['id_type'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[id_code]', 
                    [ 'id'=>'', 'label'=>'Business ID No.', 'required'=>false, 'attrs'=>[], 'description'=>'Eg. BRN, IC, Passport No., etc' ], 
                    $datas['id_code'], $view 
                ); 
            ?>
        </div>
    </div>

<?php if( current_user_cans( ['wh_admin_support'] ) ): ?>
    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[no_metric_sale]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Not selling Metric Item to Client', 'required'=>false, 'attrs'=>[],
                    'description'=>'Not selling item in metric kg/l to this client' ], 
                $datas['no_metric_sale'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[no_returnable_handling]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'No Replaceable Item Handling', 'required'=>false, 'attrs'=>[],
                    'description'=>'No Replaceable Item Handling on Delivery Order' ], 
                $datas['no_returnable_handling'], $view 
            ); 
        ?>
        </div>
    </div>
<?php endif; ?>

    <!--<div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                /*$options = options_data( apply_filters( 'wcwh_get_company', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[comp_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Company', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['comp_id'], $view 
                ); */
            ?>
        </div>
    </div>-->

    <div class='form-rows-group'>
        <h5>Billing Address</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[billing][contact_person]', 
                    [ 'id'=>'', 'label'=>'Contact Person', 'required'=>false, 'attrs'=>[] ], 
                    $datas['billing']['contact_person'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[billing][contact_no]', 
                    [ 'id'=>'contact_no', 'label'=>'Contact No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['billing']['contact_no'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[billing][address_1]', 
                    [ 'id'=>'', 'label'=>'Address', 'required'=>false, 'attrs'=>[] ], 
                    $datas['billing']['address_1'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $country = !empty( $datas['billing']['country'] )? $datas['billing']['country'] : $def_country;
                $countries = WCWH_Function::get_countries();
                $options = options_data( $countries );

                wcwh_form_field( $prefixName.'[billing][country]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Country', 'required'=>false, 
                        'attrs'=>[ 'data-state_target=".billing_country_state"' ], 
                        'class'=>['select2Strict', 'dynamicCountryState'],
                        'options'=> $options
                    ], 
                    $country, $view 
                ); 

                wcwh_form_field( $prefixName.'[billing][id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['billing']['id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $state = !empty( $datas['billing']['state'] )? $datas['billing']['state'] : '';
                $states = WCWH_Function::get_states( $country );
                if( empty( $states ) && !empty( $datas['billing']['state'] ) ) $states[ $datas['billing']['state'] ] = $datas['billing']['state'];
                $options = options_data( $states );

                wcwh_form_field( $prefixName.'[billing][state]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'State', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2Tag', 'billing_country_state'],
                        'options'=> $options
                    ], 
                    $datas['billing']['state'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[billing][city]', 
                    [ 'id'=>'', 'label'=>'City', 'required'=>false, 'attrs'=>[] ], 
                    $datas['billing']['city'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[billing][postcode]', 
                    [ 'id'=>'', 'label'=>'Postcode', 'required'=>false, 'attrs'=>[] ], 
                    $datas['billing']['postcode'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <div class='form-rows-group'>
        <h5>Delivery Address (Optional)<sup class="toolTip" title="Leave blank for same with billing address"> ? </sup></h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[shipping][contact_person]', 
                    [ 'id'=>'', 'label'=>'Contact Person', 'required'=>false, 'attrs'=>[] ], 
                    $datas['shipping']['contact_person'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[shipping][contact_no]', 
                    [ 'id'=>'', 'label'=>'Contact No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['shipping']['contact_no'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[shipping][address_1]', 
                    [ 'id'=>'', 'label'=>'Address', 'required'=>false, 'attrs'=>[] ], 
                    $datas['shipping']['address_1'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $country = !empty( $datas['shipping']['country'] )? $datas['shipping']['country'] : $def_country;
                $countries = WCWH_Function::get_countries();
                $options = options_data( $countries );

                wcwh_form_field( $prefixName.'[shipping][country]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Country', 'required'=>false, 
                        'attrs'=>[ 'data-state_target=".shipping_country_state"' ], 
                        'class'=>['select2Strict', 'dynamicCountryState'],
                        'options'=> $options
                    ], 
                    $country, $view 
                ); 

                wcwh_form_field( $prefixName.'[shipping][id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['shipping']['id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $state = !empty( $datas['shipping']['state'] )? $datas['shipping']['state'] : '';
                $states = WCWH_Function::get_states( $country );
                if( empty( $states ) && !empty( $datas['shipping']['state'] ) ) $states[ $datas['shipping']['state'] ] = $datas['shipping']['state'];
                $options = options_data( $states );

                wcwh_form_field( $prefixName.'[shipping][state]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'State', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2Tag', 'shipping_country_state'],
                        'options'=> $options
                    ], 
                    $datas['shipping']['state'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[shipping][city]', 
                    [ 'id'=>'', 'label'=>'City', 'required'=>false, 'attrs'=>[] ], 
                    $datas['shipping']['city'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[shipping][postcode]', 
                    [ 'id'=>'', 'label'=>'Postcode', 'required'=>false, 'attrs'=>[] ], 
                    $datas['shipping']['postcode'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>