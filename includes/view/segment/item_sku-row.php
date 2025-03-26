<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_item';

?>

<div class="row serial2_row" >
    <div class="col-md-9">
        <?php 
            wcwh_form_field( $prefixName.'[serial2][]', 
                [ 'id'=>'serial2', 'label'=>'', 'required'=>false, 'attrs'=>[], 'placeholder'=>'Extra Gtin No.' ], 
                '{serial2}', $view 
            ); 
        ?>
    </div>
  
    <div class="col-md-3">
    <?php if( !$args['isView'] ): ?>
        <a class="btn btn-sm btn-none-delete remove-row" data-remove=".serial2_row" title="Remove"><i class="fa fa-trash-alt"></i></a>
    <?php endif; ?>
    </div>
</div>