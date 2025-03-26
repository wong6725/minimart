<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_form';
?>

<div class="form-row">
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[account_holder]', [ 'id'=>'', 'label'=>'Beneficiary Name', 'required'=>true, 'attrs'=>[] ], '{account_holder}', $view); ?>
    </div>
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[sender_contact]', [ 'id'=>'', 'label'=>'Sender\' Contact No', 'required'=>false, 'attrs'=>[] ], '{sender_contact}', $view); ?>
    </div>
</div>

<div class="form-row">
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[bank]', [ 'id'=>'', 'label'=>'Beneficiary Bank', 'required'=>true, 'attrs'=>[] ], '{bank}', $view); ?>
    </div>
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[account_no]', [ 'id'=>'', 'label'=>'Bank Account No', 'required'=>true, 'attrs'=>[] ], '{account_no}', $view); ?>
    </div>
</div>

<div class="form-row">
    <div class="col form-group">
        <?php
        $countries = WCWH_Function::get_countries();
        $options = options_data( $countries ); 
        wcwh_form_field( $prefixName.'[bank_country]', [ 'id'=>'', 'type'=>'select', 'label'=>'Beneficiary Bank Country', 'required'=>false, 'attrs'=>[],'class'=>['select2Strict'], 'options'=> $options ], '{bank_country}', $view); ?>
    </div>
    <div class="col form-group">
        <?php
        $currency = [];
        $filters = ['from_currency' => 'MYR'];
        $temp_options = options_data( apply_filters( 'wcwh_get_latest_exchange_rate', $filters, $order, false, [] ), 'to_currency', [ 'to_currency' ] );
        //$currency_options = options_data( get_woocommerce_currencies() ) ;
        $currencies = get_woocommerce_currencies();
        foreach ($currencies as $key => $value) 
        {
            if($temp_options[$key])
            {
                $currency[$key] = $value;
            }
        }
        $options = options_data( $currency ); 
        wcwh_form_field( $prefixName.'[currency]', [ 'id'=>'currency_val', 'type'=>'select', 'label'=>'From MYR To: ', 'required'=>true, 'attrs'=>[],'class'=>['select2Strict', 'CurrencySelect'],'options'=> $options ], '{currency}', $view); ?>
    </div>
</div>

<div class="form-row">
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[bank_address]', [ 'id'=>'', 'label'=>'Beneficiary Bank Address', 'required'=>false, 'attrs'=>[] ], '{bank_address}', $view); ?>
    </div>
</div>