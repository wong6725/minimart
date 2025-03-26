<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>{inconsistent}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][price_value]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '{price_value}', $view 
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