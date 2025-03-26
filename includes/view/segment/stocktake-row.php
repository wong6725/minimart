<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>{stock_bal_qty}</td>
    <td>{stock_bal_unit}</td>
    <td>
        <?php 
            if( $args['edit'] ):

            wcwh_form_field( $prefixName.'[{i}][bqty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                '{bqty}', $view 
            ); 

            endif;

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
            if( $args['edit'] ):

            wcwh_form_field( $prefixName.'[{i}][bunit]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{bunit}', $view 
            ); 

            endif;
        ?>
    </td>
    <td>
        <?php
            if( $args['edit'] ):

            wcwh_form_field( $prefixName.'[{i}][plus_sign]', 
                [ 'id'=>'plus_sign ', 'type'=>'select', 'label'=>'', 'required'=>true, 'attrs'=>[], 'class'=> [ 'enterNextRow' ],
                    'options'=> [ '+'=>"In +", "-"=>"Out -" ]
                ], 
                isset( $args['plus_sign'] )? $args['plus_sign'] : '{plus_sign}', 1 
            ); 

            endif;
        ?>
    </td>
    <td>
        <?php
            wcwh_form_field( $prefixName.'[{i}][adjust_qty]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                '{adjust_qty}', 1 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][adjust_unit]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{adjust_unit}', 1 
            ); 
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>