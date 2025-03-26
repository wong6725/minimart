<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';

//-------- value for respective input field
$temp['account_holder']='';
$temp['sender_contact']='';						   
$temp['bank']=''; 
$temp['bank_country']='';
$temp['currency']='';  
$temp['bank_address']=''; 
$temp['account_no']='';


//------------- Reference Value for data- attribute of Default Radio Button (Add New)
$Naccount_holder = '';
$Nsender_contact = '';					  
$Nbank = '';
$Nbank_country = 'ID';
$Ncurrency = 'IDR';
$Nbank_address = '';
$Naccount_no = '';
$data_tpl = $args['rowTpl'].'TPL';

$spancolorindicate =''; //---- status indication for account details (view only)
$radiohtml = ''; //------ concatenate and store all the radio button html
if($args['action'] =='update_api') $disabled = 'disabled';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate>
<?php endif; ?>
<!------------------------------------------------------------------------------------------------------------------------>

<!-----------------------------------------Customer/ Doc Date Row------------------------------------------------->
<div class="header-container">
    <h5>Sender Info</h5>        
    <div class="form-row">
        <div class="col form-group">
            <?php if( $datas['doc_id'] ): ?>
                <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
            <?php endif; ?>

            <?php
                wcwh_form_field( $prefixName.'[sender_name]', 
                    [ 'id'=>'', 'label'=>'Sender Name', 'required'=>true, 'attrs'=>[], 'class'=>[''] ], 
                    $datas['sender_name'], $view 
                );

                wcwh_form_field( $prefixName.'[customer_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['customer_id'], $view 
                );

                wcwh_form_field( $prefixName.'[customer_serial]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['customer_serial'], $view 
                );

                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['warehouse_id'], $view 
                ); 

                wcwh_form_field( $prefixName.'[bankAccID]', 
                    [ 'id'=>'', 'type'=>'hidden' ,'label'=>'Bank Acc ID', 'required'=>true, 'attrs'=>[] ], 
                    $datas['bankAccID'], $view 
                );
            ?>
        </div>
        <div class="col form-group">
        	<?php 
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );
                else $datas['doc_date'] = date( 'Y-m-d' );
                if( !empty( $datas['ref_doc_date'] ) )
                {
                    $min_date = date( 'm/d/Y', strtotime( $datas['ref_doc_date'] ) );
                    $min_date = 'data-dd-min-date="'.$min_date.'"';

                    $max_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );
                    $max_date = 'data-dd-max-date="'.$max_date.'"';
                }
                else
                {
                    $min_date = ''; $max_date = '';
                } 

                wcwh_form_field( $prefixName.'[doc_date]', [ 'id'=>'', 'type'=>'text', 'label'=>'Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"', $min_date, $max_date ], 'class'=>['doc_date', 'picker', 'exc_date'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", ($disabled)? true : $view
                ); 
            ?>
        </div>  
    </div>  
</div>
<!-----------------------------------------End of Customer/ Doc Date Row------------------------------------------------->

<!-----------------------------------------	User Remittance Service Account Info ------------------------------------------------->

<div class="detail-container">
	<h5>Bank Account Details</h5>
	<?php 
    
	if( !$view )
	{
		$selection = 'checked';
		$label = ' Add New Bank Account ';
		$radioElem = [];
		//----------------------- Existing Account Info - Radio Button --------------------------//
		if($datas['bank_account'])
		{
			foreach ($datas['bank_account'] as $key) 
			{
				if($datas['new_bankinfo_id'] && $datas['new_bankinfo_id']==$key['id']) continue;

				$RadioLabel = $key['account_no']. ' - '.$key['bank'];

				if($args['action'] == 'save')
                {
                    $first_index = reset($datas['bank_account']);
                    $condition = isset($datas['default_selection'])? '$key["default_selection"]':'$key === $first_index';            
                }
                else if($args['action'] == 'update' || $args['action'] == 'update_api')
                {
                    $condition = '$datas["bankAccID"] && $datas["bankAccID"] == $key["id"]';
                }

                if( $condition && (eval("return $condition;")) )
                {
                	$temp['account_holder'] = $key['account_holder'];
                    $temp['sender_contact'] = $key['sender_contact'];
                    $temp['bank'] = $key['bank'];  
                    $temp['bank_country'] = $key['bank_country'];
                    $temp['currency'] = $key['currency'];  
                    $temp['bank_address'] = $key['bank_address']; 
                    $temp['account_no'] = $key['account_no'];

                    $relem = '<div
                    	id="radio-data-'.$key["id"].'"
                    	data-account_holder="'.$key["account_holder"].'"
                    	data-sender_contact="'.$key["sender_contact"].'"
                    	data-bank="'.$key["bank"].'"
                    	data-bank_country="'.$key["bank_country"].'"
                    	data-currency="'.$key["currency"].'"
                    	data-bank_address="'.$key["bank_address"].'"
                    	data-account_no="'.$key["account_no"].'"
                    ></div>';

                    $relem .= '<input class="form-control dynamic-radio" name="'.$prefixName.'[bankAccID]" type="radio" value="'.$key["id"].'" data-source="radio-data-'.$key["id"].'" data-tpl="'.$data_tpl.'" data-target="#appendRow" '.$selection.' '.$disabled.'>';
                    $relem .= '<label class="control-label" for="'.$prefixName.'[bankAccID]"> '.$RadioLabel.'</label>';

                    $radioElem[] = $relem;
                    $selection = '';
                }
                else
                {
                	$relem = '<div
                    	id="radio-data-'.$key["id"].'"
                    	data-account_holder="'.$key["account_holder"].'"
                    	data-sender_contact="'.$key["sender_contact"].'"
                    	data-bank="'.$key["bank"].'"
                    	data-bank_country="'.$key["bank_country"].'"
                    	data-currency="'.$key["currency"].'"
                    	data-bank_address="'.$key["bank_address"].'"
                    	data-account_no="'.$key["account_no"].'"
                    ></div>';

                    $relem .= '<input class="form-control dynamic-radio" name="'.$prefixName.'[bankAccID]" type="radio" value="'.$key["id"].'"   data-source="radio-data-'.$key["id"].'" data-tpl="'.$data_tpl.'" data-target="#appendRow" '.$disabled.'>';
                    $relem .= '<label class="control-label" for="'.$prefixName.'[bankAccID]">'.$RadioLabel.'</label>';

                    $radioElem[] = $relem;

                }  
			}
		}
		//----------------------- End of Existing Account Info - Radio Button --------------------------//

		//----------------------- Default Radio Button (Create New)--------------------------//
		if($datas['bankAccID'] == 'new' || ($datas['bankAccID'] != 'new' && $datas['new_bankinfo_id']))
		{
			$label = ' Bank Account Info';
            
			$temp['account_holder'] = $datas['account_holder'];
            $temp['sender_contact'] = $datas['sender_contact'];
            $temp['bank'] = $datas['bank'];  
            $temp['bank_country'] = $datas['bank_country'];
            $temp['currency'] = $datas['currency'];  
            $temp['bank_address'] = $datas['bank_address']; 
            $temp['account_no'] = $datas['account_no'];
            
            $Naccount_holder = $datas['account_holder'];
            $Nsender_contact = $datas['sender_contact'];
            $Nbank = $datas['bank'];
            $Nbank_country = $datas['bank_country'];
            $Ncurrency = $datas['currency'];
            $Nbank_address = $datas['bank_address'];
            $Naccount_no = $datas['account_no']; 
		}
        
		$relem = '<div
                    id="radio-data"
                    data-account_holder="'.$Naccount_holder.'"
                    data-sender_contact="'.$Nsender_contact.'"
                    data-bank="'.$Nbank.'"
                    data-bank_country="'.$Nbank_country.'"
                    data-currency="'.$Ncurrency.'"
                    data-bank_address="'.$Nbank_address.'"
                    data-account_no="'.$Naccount_no.'"
                    ></div>';
        
        $relem .= '<input class="form-control dynamic-radio" name="'.$prefixName.'[bankAccID]" type="radio" value="new" data-tpl="'.$data_tpl.'" data-source="radio-data" data-target="#appendRow" '.$selection.' '.$disabled.'>';
        $relem .= '<label class="control-label" for="'.$prefixName.'[bankAccID]">'.$label.'</label>';

        $radioElem[] = $relem;
      
        //----------------------- End of Default Radio Button (Create New)--------------------------//

        //----------------------------  Compile Radio Html       -------------------------//
		$radio_count = count($radioElem);
        if( $radio_count % 2 == 0 ) $loop_count = $radio_count / 2;
        else $loop_count = ( $radio_count / 2 ) + 1;
        $track_count = 0;
        for( $i = 0; $i < $loop_count; $i++ )
        {
             $ini_count = $track_count;
             $radiohtml .= '<div class = "row">';
             for( $j = $track_count; $j < $radio_count; $j++ )
             {
                if($j == $ini_count+2) break;
                $radiohtml .= '<div class = "col form-group overflow-hidden">';
                $radiohtml .= $radioElem[$j];
                $radiohtml .= '</div>';

                $track_count++;
             }
             $radiohtml .= '</div>';

        }
        //----------------------------  End of Compile Radio Html       -------------------------//
	}

    $view_restriction = ($args['action'] == 'update_api')? true : $view;
    if($view_restriction)
    {
        $countries = WCWH_Function::get_countries();
        $currencies = get_woocommerce_currencies();
        
        foreach( $temp as $key => $value)
        {
            /*
            if($temp[$key] && $key == 'bank_country')
            {
                $temp[$key] = $countries[ $temp[$key] ];
            }*/

            if($temp[$key] && $key == 'currency')
            {
                $tempRest[$key] = $currencies[ $temp[$key] ];                
            }
        }
    }
   
    if($view)
    {
        if($datas['bank_account'] && !$datas['bank_account']['status'])
        {
            $spancolorindicate = 'style="color:#e60000;"';
        }

        $countries = WCWH_Function::get_countries();
        $currencies = get_woocommerce_currencies();

        foreach( $temp as $key => $value)
        {
            if(isset($datas['bank_account'][$key]) && $datas[$key] != $datas['bank_account'][$key])
            {
                if( $key == 'bank_country')
                {
                    $temp[$key] = $countries[ $datas[$key] ]. ' ( lastest: '.$countries[ $datas['bank_account'][$key] ].' )';
                }
                else if( $key == 'currency' )
                {
                    $temp[$key] = $currencies[ $datas[$key] ]. ' ( lastest: '.$currencies[ $datas['bank_account'][$key] ].' )';
                }
                else
                {
                   $temp[$key] = $datas[$key].' ( lastest: '.$datas['bank_account'][$key].' )'; 
               }                
            }

            else if($datas[$key] == $datas['bank_account'][$key])
            {
                $temp[$key] = $datas['bank_account'][$key];
                if($key == 'bank_country' )
                {
                    $temp[$key] = $countries[ $datas['bank_account'][$key] ];
                }
                else if($key == 'currency')
                {
                    $temp[$key] = $currencies[ $datas['bank_account'][$key] ];
                }
            }
            else
            {
                $temp[$key] = $datas[$key];
                if($key == 'bank_country')
                {
                    $temp[$key] = $countries[ $datas[$key] ];
                }
                else if($key == 'currency')
                {
                    $temp[$key] = $datas[$key]; 
                }
            }

        }
    }
	?>

    <?php echo $radiohtml; //- radio button html -// ?>
    <div id="appendRow">
        <div class="form-row">
            <div class="col form-group">
                <?php wcwh_form_field( $prefixName.'[account_holder]', [ 'id'=>'', 'label'=>'Beneficiary Name', 'required'=>true, 'attrs'=>[$spancolorindicate] ], $temp['account_holder'], $view); ?>                
            </div>
            <div class="col form-group">
                <?php wcwh_form_field( $prefixName.'[sender_contact]', [ 'id'=>'', 'label'=>'Sender\' Contact No', 'required'=>true, 'attrs'=>[$spancolorindicate] ], $temp['sender_contact'], $view); ?>                
            </div>                         
        </div>

        <div class="form-row">
            <div class="col form-group">
                <?php wcwh_form_field( $prefixName.'[bank]', [ 'id'=>'', 'label'=>'Beneficiary Bank', 'required'=>true, 'attrs'=>[$spancolorindicate] ], $temp['bank'], $view); ?>  
            </div>
            <div class="col form-group">
                <?php wcwh_form_field( $prefixName.'[account_no]', [ 'id'=>'', 'label'=>'Bank Account No', 'required'=>true, 'attrs'=>[$spancolorindicate], 'class'=>['numonly', 'positive-integer']], $temp['account_no'], $view); ?>   
            </div>            
        </div>

        <div class="form-row">
            <div class="col form-group">
                <?php
                    $countries = WCWH_Function::get_countries();
                    $options = options_data( $countries );
                    if($view)
                    {
                        wcwh_form_field( $prefixName.'[bank_country]', [ 'id'=>'', 'label'=>'Beneficiary Bank Country', 'required'=>false, 'attrs'=>[$spancolorindicate] ], $temp['bank_country'], true);
                    }
                    else
                    {
                        wcwh_form_field( $prefixName.'[bank_country]', [ 'id'=>'', 'type'=>'select', 'label'=>'Beneficiary Bank Country', 'required'=>false, 'attrs'=>[$spancolorindicate],'class'=>['select2Strict'], 'options'=> $options ], ( $temp['bank_country'] )? $temp['bank_country'] : 'ID', $view);
                    }
                ?>                
            </div>
            <div class="col form-group">
                <?php
                    if($view_restriction)
                    {
                        wcwh_form_field( $prefixName.'[currency]', [ 'id'=>'', 'label'=>'From MYR To', 'required'=>false, 'attrs'=>[$spancolorindicate] ], ( $tempRest['currency'] )? $tempRest['currency'] : $temp['currency'], true);
                    }
                    else
                    {
                        $currency = [];
                        $filters = ['from_currency' => 'MYR'];
                        if($datas['doc_date'])
                        {
                            $filters['effective_date'] = $datas['doc_date'];
                            $filters['flag'] = 1;

                            $order = ['since'=>'DESC','id'=>'DESC'];
                        }
                        $temp_options = options_data( apply_filters( 'wcwh_get_latest_exchange_rate', $filters, $order, false, [] ), 'to_currency', [ 'to_currency' ] );
                        
                        $currencies = get_woocommerce_currencies();
                        foreach ($currencies as $key => $value) 
                        {
                            if($temp_options[$key])
                            {
                                $currency[$key] = $value;
                            }
                        }
                        
                        $options = options_data( $currency );
                        wcwh_form_field( $prefixName.'[currency]', [ 'id'=>'currency_val', 'type'=>'select', 'label'=>'From MYR To: ', 'required'=>true, 'attrs'=>[$spancolorindicate],'class'=>['select2Strict', 'CurrencySelect'], 'options'=> $options ], ( $temp['currency'] )? $temp['currency'] : 'IDR', $view_restriction );
                    }
                ?>                
            </div>            
        </div>

        <div class="form-row">
            <div class="col form-group">
                <?php wcwh_form_field( $prefixName.'[bank_address]', [ 'id'=>'', 'label'=>'Beneficiary Bank Address', 'required'=>false, 'attrs'=>[$spancolorindicate] ], $temp['bank_address'], $view); ?>                  
            </div>            
        </div>
    </div>
    <?php
    //--- hidden currencies options for filtering purpose
    //$currency_options = options_data( get_woocommerce_currencies() ) ;
    //wcwh_form_field( '', [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'class'=>['currencyRef'], 'options'=> $currency_options ], '', $view); 
    ?>
</div>



<!------------------------------------End of User Remittance Service Account Info--------------------------------------->

<!------------------------------------Monetary Section--------------------------------------->
<?php
$view_restriction = ($args['action'] == 'update_api')? true : $view;
if($datas['amount']) $amount = 'RM '.number_format($datas['amount'], 2, ".", ",");
if($datas['exchange_rate']) $exchange_rate = number_format($datas['exchange_rate'], 2, ".", ","); //-----for view only

if($datas['service_charge']) $service_charge = 'RM '.number_format($datas['service_charge'], 2, ".", ","); //-----for view only
if($datas['total_amount']) $total_amount = 'RM '.number_format($datas['total_amount'], 2, ".", ",");

if($datas['convert_amount']) $convert_amount = get_woocommerce_currency_symbol($temp['currency']).' '.number_format($datas['convert_amount'], 2, ".", ",");
?>

<div class="detail-container">
    <div class="form-row">
        <div class="col form-group">
            <?php 
            
            wcwh_form_field( '', [ 'id'=>'', 'label'=>'Amount', 'required'=>true, 'attrs'=>['data-set_hidden = "#amount_hidden"', 'data-currency = "MYR"'], 'class'=>['numonly', 'Bcalculation', 'base_int'] ], $amount, $view_restriction);
            
            if(!$view && !$view_restriction)
            {
                wcwh_form_field( $prefixName.'[amount]', [ 'id'=>'amount_hidden', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], $datas['amount'], $view);
            }             
            ?>
        </div>

        <div class="col form-group">
            <?php
            if($view_restriction)
            {
                wcwh_form_field( $prefixName.'[service_charge]', [ 'id'=>'', 'label'=>'Service Charge', 'required'=>false, 'attrs'=>[] ], $service_charge, true);
            }
            else
            {
                if ( !class_exists( "WCWH_BankInService_Class" ) ) include_once( WCWH_DIR . "/includes/classes/bankinservice.php" );
                if ( !class_exists( "WCWH_ServiceCharge_Class" ) ) include_once( WCWH_DIR . "/includes/classes/servicecharge.php" );
                $Logic = new WCWH_BankInService_Class( $this->db_wpdb );
                $Inst = new WCWH_ServiceCharge_Class( $this->db_wpdb );
                $type = $Logic->getDocumentType();

                $servicecharge = $Inst->get_infos([ 'type'=>$type, 'status'=>1 ]);
                if($datas['service_charge_id']) 
                    $rec_servicecharge = $Inst->get_infos([ 'type'=>$type, 'id'=>$datas['service_charge_id'] ], [], true);

                echo '<select name="'.$prefixName.'[service_charge]" class="select2 Bcalculation scharge" data-selectrefclass="schargeRef" data-allow-clear="true" data-placeholder="From Amount - To Amount - Charge(RM)" disabled required>';

                $sc_options ='';
                if($rec_servicecharge) $esc_selection = false; //---- check and match the record of sc option
                if($servicecharge)
                {
                    $sc_selection = '';
                    $sc_options .= '<option></option>';
                    foreach( $servicecharge as $sc )
                    {
                        if( $rec_servicecharge && $rec_servicecharge['id'] == $sc['id'])
                        {
                            $sc_selection = 'selected';
                            $esc_selection = true;
                        } 
                        else $sc_selection = '';

                        $sc_options .= '<option
                        value = "'.$sc["charge"].'"
                        data-from_amt = "'.$sc["from_amt"].'"
                        data-to_amt = "'.$sc["to_amt"].'"
                        data-from_currency = "'.$sc["from_currency"].'"
                        data-to_currency = "'.$sc["to_currency"].'"
                        data-scid = "'.$sc["id"].'"
                        data-charge = "'.$sc["charge"].'"
                        '.$sc_selection.'>Range: '.$sc["from_amt"].' - '.$sc["to_amt"].' Charge: RM '.$sc['charge'].'</option>';
                    }
                }

                if($rec_servicecharge && isset($esc_selection) && !$esc_selection )
                {
                     $sc_options .= '<option 
                        value="'.$datas["service_charge"].'"
                        data-from_amt = "'.$rec_servicecharge["from_amt"].'"
                        data-to_amt = "'.$rec_servicecharge["to_amt"].'"
                        data-from_currency = "'.$sc["from_currency"].'"
                        data-to_currency = "'.$sc["to_currency"].'"
                        data-scid = "'.$rec_servicecharge["id"].'"
                        data-charge = "'.$rec_servicecharge["charge"].'" 
                        selected
                        >'.'Range: '.$rec_servicecharge['from_amt']. ' - '.$rec_servicecharge['to_amt'].' Charge: RM '.$datas['service_charge'].'</option>';
                }
                echo $sc_options;
                echo '</select>';
                echo '<label class="form-label" for="'.$prefixName.'[service_charge]">Service Charge <span class="required toolTip" title="required">*</span></label>';

                //------hidden select field for filtering purpose, orginal data reference
                echo '<select class="schargeRef" hidden>'.$sc_options.'</select>';

                //hidden id
                //wcwh_form_field( $prefixName.'[service_charge]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['service_charge'], 'attrs'=>[] ], $datas['service_charge'], $view);
               // wcwh_form_field( $prefixName.'[service_charge_id]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['service_charge_id'], 'attrs'=>[] ], $datas['service_charge_id'], $view);
            }

            wcwh_form_field( $prefixName.'[service_charge]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['service_charge'], 'attrs'=>[] ], $datas['service_charge'], $view);
            wcwh_form_field( $prefixName.'[service_charge_id]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['service_charge_id'], 'attrs'=>[] ], $datas['service_charge_id'], $view);
            
            ?>            
        </div>

        <div class="col form-group">
            <?php
             wcwh_form_field( $prefixName.'[total_amount]', [ 'id'=>'', 'label'=>'Total Amount', 'required'=>false, 'attrs'=>[], 'class'=>['bcalculationtotalview'] ], $total_amount, true);
             wcwh_form_field( $prefixName.'[total_amount]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['bcalculationtotal'] ], $datas['total_amount'], $view);
            ?>            
        </div>        
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php
            if($view_restriction)
            {
                wcwh_form_field( $prefixName.'[exchange_rate]', [ 'id'=>'', 'label'=>'Exchange Rate', 'required'=>false, 'attrs'=>[] ], $exchange_rate, true);
            }
            else
            {
                $filters = ['from_currency' => 'MYR', 'to_currency' => 'IDR', 'flag'=>1];
                $order = [];
                $single = true;
                if($temp['currency'])
                {
                    $filters['to_currency'] = $temp['currency'];
                }
                if($datas['doc_date'])
                {
                    $filters['effective_date'] = $datas['doc_date'];
                    $order = ['since'=>'DESC','id'=>'DESC'];
                    $latest_exr = apply_filters( 'wcwh_get_exchange_rate', $filters, $order, $single);
                }
                else
                {
                    $filters['effective_date'] = date( 'Y-m-d ', strtotime( current_time( 'mysql' ) ) );
                    $order = ['since'=>'DESC','id'=>'DESC'];
                    $latest_exr = apply_filters( 'wcwh_get_exchange_rate', $filters, $order, $single);
                }
                
                echo '<select name="'.$prefixName.'[exchange_rate]" class="select2 Bcalculation exc_int" data-selectrefclass="exc_intRef" data-allow-clear="true" data-placeholder="From(Currency Code) - To(Currency Code) - Rate" disabled required>';

                $exr_options ='';
                $exr_options .= '<option></option>';
                
                if($latest_exr)
                {
                    if($single)
                    {
                        $exr_options .= '<option
                            value = "'.$latest_exr["rate"].'"
                            data-from_currency = "'.$latest_exr["from_currency"].'"
                            data-to_currency = "'.$latest_exr["to_currency"].'"
                            data-exrid = "'.$latest_exr["id"].'"
                            data-rate = "'.$latest_exr["rate"].'"
                            data-since = "'.$latest_exr["since"].'"
                            selected
                            >From: '.$latest_exr["from_currency"].' - '.$latest_exr["to_currency"].' Rate: '.$latest_exr['rate'].'</option>';
                    }
                    /*
                    else
                    {
                        foreach( $latest_exr as $exr )
                        {
                            $exr_options .= '<option
                                value = "'.$exr["rate"].'"
                                data-from_currency = "'.$exr["from_currency"].'"
                                data-to_currency = "'.$exr["to_currency"].'"
                                data-id = "'.$exr["id"].'"
                                data-rate = "'.$exr["rate"].'"
                                selected
                                >From: '.$exr["from_currency"].' - '.$exr["to_currency"].' Rate: '.$exr['rate'].'</option>';
                        }
                    }*/

                }
                echo $exr_options;
                echo '</select>';
                echo '<label class="form-label" for="'.$prefixName.'[exchange_rate]">Exchange Rate <span class="required toolTip" title="required">*</span></label>';

                $filters = ['from_currency' => 'MYR', 'flag' => 1, 'date'=>1];
                $order = ['since'=>'DESC','id'=>'DESC'];
                $single = false;
                if(!$datas['doc_date'])
                {
                    $filters['effective_date'] = date("Y-m-d",strtotime(current_time( 'mysql' )));
                }
        
                $all_latest_exr = apply_filters( 'wcwh_get_exchange_rate',  $filters, $order, $single);
                echo '<select class="exc_intRef" hidden>';
                if($all_latest_exr)
                {
                    foreach( $all_latest_exr as $exr )
                    {
                        echo '<option
                                value = "'.$exr["rate"].'"
                                data-from_currency = "'.$exr["from_currency"].'"
                                data-to_currency = "'.$exr["to_currency"].'"
                                data-exrid = "'.$exr["id"].'"
                                data-rate = "'.$exr["rate"].'"
                                data-since = "'.$exr["since"].'"
                                >From: '.$exr["from_currency"].' - '.$exr["to_currency"].' Rate: '.$exr['rate'].'</option>';
                    }
                }
                echo '</select>';

                //----hidden field
                //wcwh_form_field( $prefixName.'[exchange_rate]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['exchange_rate'], 'attrs'=>[] ], $datas['exchange_rate'], $view);
                //wcwh_form_field( $prefixName.'[ref_exchange_rate]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['ref_exchange_rate'], 'attrs'=>[] ], $datas['ref_exchange_rate'], $view);
            } 
            wcwh_form_field( $prefixName.'[exchange_rate]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['exchange_rate'], 'attrs'=>[] ], $datas['exchange_rate'], $view);
            wcwh_form_field( $prefixName.'[ref_exchange_rate]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'class'=> ['ref_exchange_rate'], 'attrs'=>[] ], $datas['ref_exchange_rate'], $view);

            ?>            
        </div>
        <div class="col form-group">
            <?php
            wcwh_form_field( $prefixName.'[convert_amount]', [ 'id'=>'', 'label'=>'Converted Amount', 'required'=>false, 'attrs'=>[], 'class'=>['bcalculationexcview'] ], $convert_amount, true);
            wcwh_form_field( $prefixName.'[convert_amount]', [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['bcalculationexc'] ], $datas['convert_amount'], $view_restriction);
            ?>            
        </div>
        
    </div>    
</div>



<!------------------------------------End of Monetary Section--------------------------------------->







<!------------------------------------------------------------------------------------------------------------------------>
<?php if( ! $args['get_content'] ): ?>
<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>