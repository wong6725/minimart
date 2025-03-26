<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = !empty( $args['prefixName'] )? $args['prefixName'] : '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{match_text}</td>
    <td>
        <div class="{hideitem}">
        <?php
            $options = options_data( apply_filters( 'wcwh_get_item', [ 'status'=>1 ], [], false, [ 'uom'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ] );
                
            wcwh_form_field( $prefixName.'[{i}][product_id]', 
                [ 'id'=>'', 'type'=>'select', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                    'options'=> $options
                ], 
                !empty( $args['product_id'] )? $args['product_id'] : '{product_id}', $view 
            ); 
        ?>
        </div>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][amount]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','positive-number','onfocusFocus','enterNextRow'] ], 
                '{amount}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][type]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                !empty( $args['type'] )? $args['type'] : '{type}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][match]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                !empty( $args['match'] )? $args['match'] : '{match}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{item_id}', $view 
            );
           
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>