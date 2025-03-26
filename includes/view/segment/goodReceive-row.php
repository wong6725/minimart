<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row" {row_styling}>
    <td class="num handle" rowspan="1"></td>
    <td>{item}</td>
    <td>{uom}</td>
    <td>{ref_bqty}</td>
    <td>{ref_bal}</td>
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

            wcwh_form_field( $prefixName.'[{i}][foc]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{foc}', $view 
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
            wcwh_form_field( $prefixName.'[{i}][bunit]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{bunit}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            if( current_user_cans( ['wh_admin_support', 'view_amount_wh_good_receive'] ) )
            {
                wcwh_form_field( $prefixName.'[{i}][uprice]', 
                    [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','{readonly}','enterNextRow'] ], 
                    '{uprice}', $view 
                ); 
            }
            else
            {
                wcwh_form_field( $prefixName.'[{i}][uprice]', 
                    [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                    '{uprice}', $view 
                ); 
            }
        ?>
    </td>
    <td>
        <?php 
            if( current_user_cans( ['wh_admin_support', 'view_amount_wh_good_receive'] ) )
            {
                wcwh_form_field( $prefixName.'[{i}][total_amount]', 
                    [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus', '{readonly}','enterNextRow'] ], 
                    '{total_amount}', $view 
                ); 
            }
            else
            {
                wcwh_form_field( $prefixName.'[{i}][total_amount]', 
                    [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'] ], 
                    '{total_amount}', $view 
                ); 
            }
        ?>
    </td>
<?php if( $args['setting'][ $args['section'] ]['use_expiry'] ): ?>
    <td>
        <?php 
            $min_date = date( 'm/d/Y', strtotime( ( !empty( $args['doc_date'] )? $args['doc_date'] : current_time( 'mysql' ) )." +1 day" ) );
            $min_date = 'data-dd-min-date="'.$min_date.'"';

            wcwh_form_field( $prefixName.'[{i}][prod_expiry]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', $min_date ], 'class'=>['doc_date', 'picker','enterNextRow'] ], 
                '{prod_expiry}', $view 
            );
        ?>
    </td>
    <td>
        <a class="btn btn-sm btn-none dynamic-element" data-source="" data-tpl="<?php echo $args['expiryrow']; ?>TPL" 
            data-target="#item_row"
            data-addafter="tr"
            data-fi="{i}"
            data-id="{id}" 
            data-product_id="{product_id}" 
            data-item_id="{item_id}" 
            data-bqty=""
            data-bunit=""
            data-uprice="{uprice}"
            data-ref_bal="0" 
            data-ref_doc_id="{ref_doc_id}" 
            data-ref_item_id="{ref_item_id}"
            data-prod_expiry=""
            data-fraction="{fraction}"
            data-inconsistent="{inconsistent}"
            data-item_number=""
        >+</a>
    </td>
<?php endif; ?>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>