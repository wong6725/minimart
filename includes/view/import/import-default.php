<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_field';
?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
    <div class='form-rows-group'>
        <h5>Import Option</h5>
        <p class="asterisk">Note: Please refer exported file for import format.</p>
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
                wcwh_form_field( $prefixName.'[need_header]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>