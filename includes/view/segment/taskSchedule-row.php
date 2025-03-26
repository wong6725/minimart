<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} serial2_row">
    <td class="num handle"></td>
    <td>
        <?php 
             wcwh_form_field( $prefixName.'[{i}][_serial2]', 
                [ 'type'=>'text', 'id'=>"{item_id}", 'required'=>false, 'attrs'=>[], 'placeholder'=>'Enter New Task'], 
                '{serial2}', $view 
            );
            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '1', $view 
            ); 
            wcwh_form_field( $prefixName.'[{i}][product_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '99999999', $view 
            ); 
            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{item_id}', $view 
            );
            wcwh_form_field( $prefixName.'[{i}][_item_number]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['sortable_item_number'] ], 
                '{item_number}', $view 
            );
            // wcwh_form_field( $prefixName.'[{i}][status]', 
            //     [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
            //     '{status}', $view 
            // );
        ?>
    </td>
    <td>
        <a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a>
    </td>
</tr>
