<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_form';
?>

<div class="form-row">
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[account_holder]', [ 'id'=>'', 'label'=>'Beneficiary Name', 'required'=>true, 'attrs'=>[] ], '{account_holder}', true);
            wcwh_form_field( $prefixName.'[account_holder]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    '{account_holder}', $view 
                ); 
        ?>
    </div>
        <?php wcwh_form_field( $prefixName.'[receiver_contact]', [ 'id'=>'','type'=>'hidden', 'label'=>'Receiver\' Contact No', 'required'=>true, 'attrs'=>[] ], '{receiver_contact}', true);
            wcwh_form_field( $prefixName.'[receiver_contact]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    '{receiver_contact}', $view 
                ); 
        ?>
    <div class="col form-group">
        <?php wcwh_form_field( $prefixName.'[sender_contact]', [ 'id'=>'', 'label'=>'Sender\' Contact No', 'required'=>false, 'attrs'=>[] ], '{sender_contact}', true);
            wcwh_form_field( $prefixName.'[sender_contact]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    '{sender_contact}', $view 
                ); 
        ?>
    </div>
</div>

<div class="form-row">
    <div class="col form-group">
        <?php 
        wcwh_form_field( $prefixName.'[bank]', [ 'id'=>'', 'label'=>'Beneficiary Bank', 'required'=>true, 'attrs'=>[]], '{bank}', true);
        wcwh_form_field( $prefixName.'[bank]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], '{bank}', $view);
        ?>
    </div>
    <div class="col form-group">
        <?php
        wcwh_form_field( $prefixName.'[bank_country]', [ 'id'=>'', 'label'=>'Beneficiary Bank Country', 'required'=>false, 'attrs'=>[] ], '{bank_country}', true); 
        wcwh_form_field( $prefixName.'[bank_country]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], '{bank_country}', $view);
        ?>
    </div>
        <?php 
        wcwh_form_field( $prefixName.'[bank_code]', [ 'id'=>'','type'=>'hidden', 'label'=>'Beneficiary Bank Code', 'required'=>false, 'attrs'=>[]], '{bank_code}', true);
        wcwh_form_field( $prefixName.'[bank_code]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], '{bank_code}', $view);
        ?>
</div>

<div class="form-row">
    <div class="col form-group">
        <?php 
        wcwh_form_field( $prefixName.'[bank_address]', [ 'id'=>'', 'label'=>'Beneficiary Bank Address', 'required'=>false, 'attrs'=>[]], '{bank_address}', true);
        wcwh_form_field( $prefixName.'[bank_address]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], '{bank_address}', $view); 
        ?>
    </div>
</div>

<div class="form-row">
    <div class="col form-group">
        <?php 
        wcwh_form_field( $prefixName.'[account_no]', [ 'id'=>'', 'label'=>'Bank Account No', 'required'=>true, 'attrs'=>[]], '{account_no}', true);
        wcwh_form_field( $prefixName.'[account_no]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], '{account_no}', $view);  
        ?>
    </div>
</div>

<div class="form-row">
    
    <div class="col form-group">
        <?php 
        wcwh_form_field( $prefixName.'[currency]', [ 'id'=>'', 'label'=>'From MYR To:', 'required'=>false, 'attrs'=>[] ], '{currency}', true);
        wcwh_form_field( $prefixName.'[currency]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], '{currency}', $view); 
        ?>
    </div>
</div>
