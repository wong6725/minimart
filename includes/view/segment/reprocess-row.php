<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td>{index}</td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'], 'placeholder'=>'Qty' ], 
                '{bqty}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][product_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{product_id}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{item_id}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][ref_doc_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{ref_doc_id}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][ref_item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{ref_item_id}', $view 
            ); 
           
            wcwh_form_field( $prefixName.'[{i}][row_type]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                'end_product', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            /*wcwh_form_field( $prefixName.'[{i}][bunit]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{bunit}', $view 
            );*/ 
        ?>
    </td>
    <td>
        <?php 
            /*wcwh_form_field( $prefixName.'[{i}][other_cost]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '{other_cost}', $view 
            );*/ 
        ?>
    </td>
    <td rowspan="{rowspan}">
        <?php 
            wcwh_form_field( $prefixName.'[{i}][dremark]', 
                [ 'type'=>'textarea', 'required'=>false, 'attrs'=>[], 'class'=>['onfocusFocus'] ], 
                '{dremark}', $view 
            ); 
        ?>
    </td>
    <td><!--<a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a>--></td>
</tr>