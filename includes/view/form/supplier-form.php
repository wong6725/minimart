<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];
$def_country = ( $args['def_country'] )? $args['def_country'] : 'MY';

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_supplier';
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
                [ 'id'=>'', 'label'=>'Supplier Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                $filter = [ 'not_id'=>$datas['id'], 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_supplier', $filter, [], false, [] ), 'id', [ 'code', 'name' ] );
                
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
                [ 'id'=>'', 'label'=>'Supplier Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                $datas['code'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[supplier_no]', 
                    [ 'id'=>'', 'label'=>'SAP Supplier No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['supplier_no'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[self_bill]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Self Bill E-Invoice', 'required'=>false, 'attrs'=>[],
                    'description'=>'Required self bill E-Invoice for this supplier?' ], 
                $datas['self_bill'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                $options = [ 'EI00000000010'=>'EI00000000010, Local', 'EI00000000030'=>'EI00000000030, Foreign' ];
                wcwh_form_field( $prefixName.'[tin]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Supplier TIN for Self Bill', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=>$options ], 
                    $datas['tin'], $view 
                ); 
            ?>
        </div>
    </div>

<?php if( current_user_cans( ['wh_admin_support'] ) ): ?>
    <div class="form-row">
        <div class="col form-group flex-row flex-align-center">
        <?php 
            wcwh_form_field( $prefixName.'[no_egt_handle]', 
                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'No EGT handling', 'required'=>false, 'attrs'=>[],
                    'description'=>'No EGT auto issue handling' ], 
                $datas['no_egt_handle'], $view 
            ); 
        ?>
        </div>
    </div>
<?php endif; ?>

    <!--<div class="form-row">
        <div class="col form-group">
            <?php 
                /*$options = options_data( apply_filters( 'wcwh_get_company', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[comp_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Company', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['comp_id'], $view 
                ); */
            ?>
        </div>
        <div class="col form-group">
            
        </div>
    </div>-->

    <div class='form-rows-group'>
        <h5>Supplier Address</h5>
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