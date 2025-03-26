<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row calc_source">
    <td class="num handle"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>{stocks}</td>
    <td>{def_price}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow','calc_qty'] ], 
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

            wcwh_form_field( $prefixName.'[{i}][_item_number]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['sortable_item_number'] ], 
                '{item_number}', $view 
            );

           wcwh_form_field( $prefixName.'[{i}][sprice]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['calc_price'] ], 
                '{sprice}', $view 
            );
        ?>
    </td>
    <td class="calc_amt">{sale_amt}</td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][period]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','positive-integer','onfocusFocus','enterNextRow'] ], 
                '{period}', $view 
            ); 
           
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>