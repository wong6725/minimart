<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];
$def_country = ( $args['def_country'] )? $args['def_country'] : 'MY';

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_company';
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
                [ 'id'=>'', 'label'=>'Company Name', 'required'=>true, 'attrs'=>[], 'class'=> [] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
    	<div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[code]', 
                [ 'id'=>'', 'label'=>'Company Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[regno]', 
                    [ 'id'=>'', 'label'=>'Registration No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['regno'], $view 
                ); 
            ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[custno]', 
                    [ 'id'=>'', 'label'=>'SAP Company No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['custno'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_company', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent Company', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
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
            <div class="form-row">
                <div class="col form-group">
                    <?php 
                        $options = [ 0=>'NO', 1=>'YES' ];
                        
                        wcwh_form_field( $prefixName.'[einv]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'E-Invoice Submission?', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'], 
                                'options'=> $options
                            ], 
                            $datas['einv'], $view 
                        ); 
                    ?>
                </div>
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
            </div>
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
                wcwh_form_field( $prefixName.'[msic]', 
                    [ 'id'=>'', 'label'=>'IRB MSIC Code', 'required'=>false, 'attrs'=>[], 'description'=>'' ], 
                    $datas['msic'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class='form-rows-group'>
        <h5>Company Address</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[address][contact_person]', 
                    [ 'id'=>'', 'label'=>'Contact Person', 'required'=>false, 'attrs'=>[] ], 
                    $datas['address']['contact_person'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[address][contact_no]', 
                    [ 'id'=>'', 'label'=>'Contact No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['address']['contact_no'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[address][address_1]', 
                    [ 'id'=>'', 'label'=>'Address', 'required'=>false, 'attrs'=>[] ], 
                    $datas['address']['address_1'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $country = !empty( $datas['address']['country'] )? $datas['address']['country'] : $def_country;
                $countries = WCWH_Function::get_countries();
                $options = options_data( $countries );

                wcwh_form_field( $prefixName.'[address][country]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Country', 'required'=>false, 
                        'attrs'=>[ 'data-state_target=".country_state"' ], 
                        'class'=>['select2Strict', 'dynamicCountryState'],
                        'options'=> $options
                    ], 
                    $country, $view 
                ); 

                wcwh_form_field( $prefixName.'[address][id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['address']['id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $state = !empty( $datas['address']['state'] )? $datas['address']['state'] : '';
                $states = WCWH_Function::get_states( $country );
                if( empty( $states ) && !empty( $datas['address']['state'] ) ) $states[ $datas['address']['state'] ] = $datas['address']['state'];
                $options = options_data( $states );

                wcwh_form_field( $prefixName.'[address][state]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'State', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2Tag', 'country_state'],
                        'options'=> $options
                    ], 
                    $datas['address']['state'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[address][city]', 
                    [ 'id'=>'', 'label'=>'City', 'required'=>false, 'attrs'=>[] ], 
                    $datas['address']['city'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[address][postcode]', 
                    [ 'id'=>'', 'label'=>'Postcode', 'required'=>false, 'attrs'=>[] ], 
                    $datas['address']['postcode'], $view 
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