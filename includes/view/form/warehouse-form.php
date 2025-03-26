<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];
$def_country = ( $args['def_country'] )? $args['def_country'] : 'MY';

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_warehouse';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="header-container">
    	<h5>Warehouse / Outlet Info</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[name]', 
                    [ 'id'=>'', 'label'=>'Warehouse Name', 'required'=>true, 'attrs'=>[] ], 
                    $datas['name'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[code]', 
                    [ 'id'=>'', 'label'=>'Warehouse Code', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                    $datas['code'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_warehouse', [ 'not_id'=>$datas['id'], 'status'=>1 ], [], false, [] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent Warehouse', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_company', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[comp_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Company', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2' ],
                        'options'=> $options
                    ], 
                    $datas['comp_id'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <div class='form-rows-group'>
        <h5>Warehouse Address</h5>
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

    <div class="header-container">
        <h5>Setting</h5>
        <div class="form-row">
            <div class="col form-group">
                <?php 
                    if( $args['capability'] )
                    {
                        $options = options_data( $args['capability'], 'key', [ 'title' ], '' );

                        wcwh_form_field( $prefixName.'[capability][]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'WH Capability', 'required'=>false, 
                                'attrs'=>[ 'data-placeholder="Select Options"' ], 'class'=>[ 'multiple', 'select2', 'modalSelect' ], 
                                'options'=>$options, 'multiple'=>true,
                            ], 
                            $datas['capability'], $view 
                        ); 
                    }
                ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = $args['permission']['general'];

                $warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'not_indication'=>1 ], [], false, [] );
                if( $warehouses )
                {
                    foreach( $warehouses as $wh )
                    {
                        $right = 'access_wcwh_'.$wh['code'];
                        $ques = 'Access '.$wh['name'];
                        
                        $options[$right] = $ques;
                    }
                }
                
                wcwh_form_field( $prefixName.'[permissions][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Permission To Access', 'required'=>false, 
                        'attrs'=>[ 'data-placeholder="Select Options"' ], 'class'=>[ 'multiple', 'select2', 'modalSelect' ], 
                        'options'=>$options, 'multiple'=>true,
                    ], 
                    $datas['permissions'], $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[indication]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Current System is Seller', 'required'=>false, 'attrs'=>[] ], 
                    $datas['indication'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[visible]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Visible On Menu', 'required'=>false, 'attrs'=>[] ], 
                    $datas['visible'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[view_outlet]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'View Outlet', 'required'=>false, 'attrs'=>[] ], 
                    $datas['view_outlet'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[has_pos]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Has POS System', 'required'=>false, 'attrs'=>[] ], 
                    $datas['has_pos'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[hidden]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Hide In Background', 'required'=>false, 'attrs'=>[] ], 
                    $datas['hidden'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <div class="header-container">
        <h5>Operation Setting</h5>
        <?php
            if( $datas['capability'] && in_array( 'purchase_order', $datas['capability'] ) )
            {
                echo '<div class="form-row"><div class="col form-group">';

                wcwh_form_field( $prefixName.'[po_email]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'PO Email on posting', 'required'=>false, 'attrs'=>[], 
                        'description'=>'Multi email by comma separated' ], 
                    $datas['po_email'], $view 
                ); 

                echo '</div></div>';
            }
        ?>
        <?php
            if( $datas['capability'] && in_array( 'sales_order', $datas['capability'] ) )
            {
                echo '<div class="form-row"><div class="col form-group">';

                wcwh_form_field( $prefixName.'[so_email]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'SO Email on posting', 'required'=>false, 'attrs'=>[], 
                        'description'=>'Multi email by comma separated' ], 
                    $datas['so_email'], $view 
                ); 

                echo '</div></div>';
            }
        ?>
        <?php
            if( $datas['capability'] && in_array( 'transfer_order', $datas['capability'] ) )
            {
                echo '<div class="form-row"><div class="col form-group">';

                wcwh_form_field( $prefixName.'[to_email]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'TO Email on posting', 'required'=>false, 'attrs'=>[], 
                        'description'=>'Multi email by comma separated' ], 
                    $datas['to_email'], $view 
                ); 

                echo '</div></div>';
            }
        ?>
        <?php
            if( in_array( 'delivery_order', $datas['capability'] ) )
            {
                echo '<div class="form-row"><div class="col form-group">';

                wcwh_form_field( $prefixName.'[do_email]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'DO Email on posting', 'required'=>false, 'attrs'=>[], 
                        'description'=>'Multi email by comma separated' ], 
                    $datas['do_email'], $view 
                ); 

                echo '</div></div>';
            }
        ?>
    </div>

    <div class="header-container">
        <h5>Integration / Sync Mapping</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[dbname]', 
                    [ 'id'=>'', 'label'=>'DB Name on Same Server', 'required'=>false, 'attrs'=>[] ], 
                    $datas['dbname'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[archive_dbname]', 
                    [ 'id'=>'', 'label'=>'Archieve DB Name on Same Server', 'required'=>false, 'attrs'=>[] ], 
                    $datas['archive_dbname'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[api_url]', 
                    [ 'id'=>'', 'label'=>'API Url for Sync Out', 'required'=>false, 'attrs'=>[] ], 
                    $datas['api_url'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[api_find]', 
                    [ 'id'=>'', 'label'=>'API Find', 'required'=>false, 'attrs'=>[] ], 
                    $datas['api_find'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[api_replace]', 
                    [ 'id'=>'', 'label'=>'API Replace', 'required'=>false, 'attrs'=>[] ], 
                    $datas['api_replace'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
                wcwh_form_field( $prefixName.'[client_company_code][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Client Belonging', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['client_company_code'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_supplier', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
                wcwh_form_field( $prefixName.'[supplier_company_code][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Supplier Belonging', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['supplier_company_code'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $client_opts = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
                wcwh_form_field( $prefixName.'[foodboard_client][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'FoodBoard Client Mapping', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
                        'options'=> $client_opts, 'multiple'=>1
                    ], 
                    $datas['foodboard_client'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $customer_opts = options_data( apply_filters( 'wcwh_get_customer', [ 'status'=>1 ] ), 'id', [ 'code', 'uid', 'name' ] );
                    
                wcwh_form_field( $prefixName.'[foodboard_customer][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Foodboard Customer Mapping', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $customer_opts, 'multiple'=>1
                    ], 
                    $datas['foodboard_customer'], $view 
                );
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                //$options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                    
                wcwh_form_field( $prefixName.'[estate_client][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Estate Client Mapping', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
                        'options'=> $client_opts, 'multiple'=>1
                    ], 
                    $datas['estate_client'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                //$options = options_data( apply_filters( 'wcwh_get_customer', [ 'status'=>1 ] ), 'id', [ 'code', 'uid', 'name' ] );
                    
                wcwh_form_field( $prefixName.'[estate_customer][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Estate Customer Mapping', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $customer_opts, 'multiple'=>1
                    ], 
                    $datas['estate_customer'], $view 
                );
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
               $options = options_data( apply_filters( 'wcwh_get_account_type', $filter, [], false, [] ), 'id', [ 'code' ] );
                    
                wcwh_form_field( $prefixName.'[estate_expense_acc][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Estate Expense Account Type', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['estate_expense_acc'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['mc_cutoff'] ) $mc_cutoff = date( 'm/d/Y', strtotime( $datas['mc_cutoff'] ) );
                else $mc_cutoff = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );

                wcwh_form_field( $prefixName.'[mc_cutoff]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Money Collector CutOff', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$mc_cutoff.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['mc_cutoff'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>