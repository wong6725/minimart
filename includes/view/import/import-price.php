<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
    <div class='form-rows-group'>
        <h5>Import Option</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'import', 
                    [ 'id'=>'import', 'type'=>'file', 'label'=>'Import File (.xlss, .xls)', 'required'=>true, 'attrs'=>[ 'accept=".xlsx, .xls"' ] ], 
                    '', $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <!--<div class="col form-group">
            <?php 
                /*$options = [ ','=>',', '\t'=>'tab', ';'=>';', '|'=>'|', '||'=>'||' ];

                wcwh_form_field( $prefixName.'[delimiter]', 
                    [ 'id'=>'delimiter', 'type'=>'select', 'label'=>'Delimiter', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    ',', $view 
                ); */
            ?>
            </div>-->
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[header]', 
                    [ 'id'=>'header', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'for_integrate'=>'Integrate', 'form'=>'Form' ];

                wcwh_form_field( $prefixName.'[exim_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Delimiter', 'required'=>false, 
                        'attrs'=>['data-showhide=".form_upload"'], 'class'=>['optionShowHide'], 
                        'options'=>$options
                    ], 
                    'for_integrate', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>
    </div>

    <div class="header-container form_upload form">
        <h5>Header</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[docno]', 
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                    $datas['docno'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[code]', 
                    [ 'id'=>'', 'label'=>'Price Code', 'required'=>false, 'attrs'=>[], 'class'=>['readonly'], 'description'=>'System generate' ], 
                    $datas['code'], true 
                ); 
            ?>
            <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'company'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[seller][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Seller', 'required'=>true, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['seller'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['since'] ) $since = date( 'm/d/Y', strtotime( $datas['since'] ) );

                wcwh_form_field( $prefixName.'[since]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective Date', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$since.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['since'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $schemes = get_schemes( 'pricing' );
                $options = options_data( $schemes, 'scheme', [ 'title' ], '' );
                
                if( ! $view )
                {
                    wcwh_form_field( $prefixName.'[scheme]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Price Apply To', 'required'=>false, 'attrs'=>['data-showhide=".scheme_ref"'], 'class'=>['optionShowHide'],
                            'options'=> $options
                        ], 
                        $datas['scheme'], $view 
                    ); 
                }
                else
                {
                    wcwh_form_field( $prefixName.'[scheme]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Price Apply To', 'required'=>false, 'attrs'=>[], 'class'=>[],
                            'options'=> $options
                        ], 
                        $datas['scheme'], $view 
                    ); 
                }
            ?>
            </div>
            <div class="col form-group scheme_ref default">
            <?php 
                wcwh_form_field( $prefixName.'[default]', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    $datas['default'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group scheme_ref client_code">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1, 'warehouse'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[client_code][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Target Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['client_code'], $view 
                ); 
            ?>
            </div>
            <!-- <div class="col form-group scheme_ref customer_group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_customer_group', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                
                wcwh_form_field( $prefixName.'[customer_group]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Target Customer Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['customer_group'], $view 
                ); 
            ?>
            </div>-->
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[remarks]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remarks'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>