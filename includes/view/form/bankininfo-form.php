<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_bankininfo';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php
        $filter = ['wh_code'=>$this->warehouse['code'], 'status'=>'1'];
        if( $args['seller'] ) $filter['seller'] = $args['seller'];
        $employer = apply_filters( 'wcwh_get_customer', $filter, [], false);
        $options = options_data( $employer, 'id', ['uid','code','serial','name']); 
            wcwh_form_field( $prefixName.'[customer_id]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Sender Name', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'], 'options'=> $options], 
                $datas['customer_id'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php wcwh_form_field( $prefixName.'[sender_contact]', [ 'id'=>'', 'label'=>'Sender\'s Contact No', 'required'=>false, 'attrs'=>[] ], $datas['sender_contact'], $view); ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php wcwh_form_field( $prefixName.'[account_holder]', [ 'id'=>'', 'label'=>'Beneficiary Name', 'required'=>true, 'attrs'=>[] ], $datas['account_holder'], $view); ?>
        </div>
        
        <?php wcwh_form_field( $prefixName.'[receiver]', [ 'id'=>'', 'type'=>'hidden', 'label'=>'Receiver Name', 'required'=>false, 'attrs'=>[] ], $datas['receiver'], $view); ?>
        <?php wcwh_form_field( $prefixName.'[receiver_contact]', [ 'id'=>'','type'=>'hidden', 'label'=>'Receiver\'s Contact No', 'required'=>true, 'attrs'=>[] ], $datas['receiver_contact'], $view); ?>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php wcwh_form_field( $prefixName.'[bank]', [ 'id'=>'', 'label'=>'Beneficiary Bank Name', 'required'=>true, 'attrs'=>[] ], $datas['bank'], $view); ?>
        </div>
        <div class="col form-group">
            <?php
            $country = !empty( $datas['bank_country'] )? $datas['bank_country'] : 'MY';
            $countries = WCWH_Function::get_countries();
            $options = options_data( $countries ); 
            wcwh_form_field( $prefixName.'[bank_country]', [ 'id'=>'', 'type'=>'select', 'label'=>'Beneficiary Bank Country', 'required'=>false, 'attrs'=>[],'class'=>['select2Strict'], 'options'=> $options ], $datas['bank_country'], $view); ?>
        </div>
    </div>

    
    <?php wcwh_form_field( $prefixName.'[bank_code]', [ 'id'=>'', 'type'=>'hidden', 'label'=>'Beneficiary Bank Code', 'required'=>false, 'attrs'=>[] ], $datas['bank_code'], $view); ?>
    
    <div class="form-row">
        <div class="col form-group">
            <?php wcwh_form_field( $prefixName.'[bank_address]', [ 'id'=>'', 'type'=>'textarea','label'=>'Beneficiary Bank Address', 'required'=>false, 'attrs'=>[] ], $datas['bank_address'], $view); ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php wcwh_form_field( $prefixName.'[account_no]', [ 'id'=>'', 'label'=>'Bank Account No', 'required'=>true, 'attrs'=>[], 'class'=>['numonly', 'positive-integer'] ], $datas['account_no'], $view); ?>
        </div>
    </div>

    
    <div class="form-row">
        <div class="col form-group">
            <?php
            $filters = ['from_currency' => 'MYR'];
            $options = options_data( apply_filters( 'wcwh_get_latest_exchange_rate', $filters, [], false, ['to_currency'] ), 'to_currency', [ 'to_currency' ] );
    
            $all_currency = get_woocommerce_currencies();
            foreach ($all_currency as $key => $value)
            {
                if($key == $options[$key])
                {
                    $currency[$key] = $value;
                }
            }
            $currency_options = options_data( $currency ) ;
            wcwh_form_field( $prefixName.'[currency]', [ 'id'=>'', 'type'=>'select', 'label'=>'From MYR To:', 'required'=>true, 'attrs'=>[],'class'=>['select2Strict'], 'options'=> $currency_options ], $datas['currency'], $view); ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[desc]', 
                [ 'id'=>'', 'type'=>'textarea','label'=>'Remark', 'required'=>false, 'attrs'=>[], ], 
                $datas['desc'], $view 
            ); 
        ?>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName ?>[id]" value="<?php echo $datas['id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>