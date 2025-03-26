<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" class="row{fi} line-{i} follow_dragged">
    <td></td>
    <td></td>
    <td></td>
    <td></td>
    <td></td>
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

            wcwh_form_field( $prefixName.'[{i}][uprice]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                '{uprice}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][bunit]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{bunit}', $view 
            ); 
        ?>
    </td>
    <td>
    </td>
    <td>
    </td>
<?php if( $args['setting'][ $args['section'] ]['use_expiry'] ): ?>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][prod_expiry]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker','enterNextRow'] ], 
                '{prod_expiry}', $view 
            );
        ?>
    </td>
    <td>
    </td>
<?php endif; ?>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>