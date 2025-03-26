<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row">
    <td class="num handle"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <td class="{stocks_clr}">{stocks}</td>
    <td class="{stocks_clr}">{ref_bqty}</td>
    <td class="{stocks_clr}">{ref_bal}</td>
    <td>
        <?php
            $options = options_data( apply_filters( 'wcwh_get_item', [ 'status'=>1 ], [], false, [ 'uom'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ] );
                
            wcwh_form_field( $prefixName.'[{i}][to_product_id]', 
                [ 'id'=>'', 'type'=>'select', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                !empty( $args['to_product_id'] )? $args['to_product_id'] : '{to_product_id}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][fbqty]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                '{fbqty}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
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
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>