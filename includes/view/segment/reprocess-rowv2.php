<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row">
    <td class="num handle"></td>
    <td>{mat}</td>
    <td>{mat_uom}</td>
    <td class="{stocks_clr} material_stock">{mat_stock}</td>
    <td class="{stocks_clr}">{mat_ref_bqty}</td>
    <td class="{stocks_clr}">{mat_ref_bal}</td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][material_uqty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>['data-i = "{i}"'], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow', 'tolerance_counter'] ], 
                '{material_uqty}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][material_ids]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{mat_id}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][material_req]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{mat_req}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][product_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{id}', $view 
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
            
            wcwh_form_field( $prefixName.'[{i}][ref_bal]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{ref_bal}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][_item_number]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['sortable_item_number'] ], 
                '{item_number}', $view 
            );
        ?>
    </td>
    <td>
        <?php
            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>['data-i = "{i}"', 'data-tolerance = "{tolerance}"', 'data-tolerance_rounding = "{tolerance_rounding}"'], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow','tolerance_validate'], 'placeholder'=>'{tolerance_pholder}'], 
                '{bqty}', $view 
            );
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>