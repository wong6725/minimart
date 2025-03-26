<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>{price_code}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][uprice]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','positive-number','onfocusFocus','enterNextRow'], 
                    'placeholder'=>'{uprice}' ], 
                '', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][price]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','positive-number','onfocusFocus','enterNextRow'],
                    'placeholder'=>'{price}' ], 
                '', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][qty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','positive-integer','onfocusFocus','enterNextRow'] ], 
                '{qty}', $view 
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
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][metric]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{metric}', $view 
            ); 
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>