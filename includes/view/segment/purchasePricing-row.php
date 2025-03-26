<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
$strg_id = ( $args['inventory'] )? $args['inventory'] : 0;
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <?php if( $strg_id ): ?>
    <td>{inconsistent}</td>
    <td>{avg_cost}</td>
    <?php endif; ?>
    <td>{latest_cost}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][unit_price]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','positive-number','onfocusFocus','enterNextRow'] ], 
                '{unit_price}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][product_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{product_id}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{item_id}', $view 
            );
           
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>