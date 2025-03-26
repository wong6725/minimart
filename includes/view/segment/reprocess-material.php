<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td>{index}</td>
    <td>[Material] {item}</td>
    <td>{uom}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][bunit]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'], 'placeholder'=>'Usage' ], 
                '{bunit}', $view 
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

            wcwh_form_field( $prefixName.'[{i}][ref_bqty]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{ref_bqty}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][ref_base]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{ref_base}', $view 
            ); 
            
            wcwh_form_field( $prefixName.'[{i}][row_type]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                'material_usage', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][lqty]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'], 'placeholder'=>'Leftover' ], 
                '{lqty}', $view 
            );
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][wqty]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'], 'placeholder'=>'Spoil' ], 
                '{wqty}', $view 
            );
        ?>
    </td>
    <td><!--<a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a>--></td>
</tr>