<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row">
    <td class="num handle"></td>
    <td>{item}</td>
    <!--<td>{uom}</td>-->
    <td>{ref_bqty}</td>
    <!--<td>{ref_bal}</td>-->
    <td>
        <?php
            if( !empty($args['options']) )
            {
                $options = options_data( $args['options'], 'id', [ 'code', 'name', '_uom_code', 'converse' ] );
                
                wcwh_form_field( $prefixName.'[{i}][to_product_id]', 
                    [ 'id'=>'', 'type'=>'select', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    !empty( $args['to_product_id'] )? $args['to_product_id'] : $args['product_id'], $view 
                ); 
            }
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
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
            wcwh_form_field( $prefixName.'[{i}][amount]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '{amount}', $view
            ); 
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>