<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';

$needRemark = ( $args['setting'][ $args['section'] ]['dremark'] )? true : false;
?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row">
    <td class="num cursorGrab handle" <?php echo ( $needRemark )? 'rowspan="2"' : '' ?> ></td>
    <td <?php echo ( $needRemark )? 'rowspan="2"' : '' ?> >
        <?php
            wcwh_form_field( $prefixName.'[{i}][custom_item]', 
                [ 'type'=>'textarea', 'required'=>true, 'attrs'=>[], 'class'=>['onfocusFocus','enterNextRow'], 'placeholder'=>'Custom Item Name' ], 
                '{custom_item}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php
            $options = options_data( apply_filters( 'wcwh_get_uom', [], [], false, [] ), 'code', [ 'code' ] );
                
            wcwh_form_field( $prefixName.'[{i}][uom_id]', 
                [ 'id'=>'', 'type'=>'select', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                    'options'=> $options
                ], 
                !empty( $args['uom_id'] )? $args['uom_id'] : '{uom_id}', $view 
            ); 
        ?>
    </td>
    <td>{def_price}</td>
    <td class="{stocks_clr}">{stocks}</td>
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

            wcwh_form_field( $prefixName.'[{i}][_item_number]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['sortable_item_number'] ], 
                '{item_number}', $view 
            );
           
        ?>
    </td>
    <!-- <td>
        <?php 
            /*wcwh_form_field( $prefixName.'[{i}][sunit]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','positive-number','{inconsistent}','onfocusFocus','enterNextRow'] ], 
                '{sunit}', $view 
            );*/ 
        ?>
    </td> -->
    <?php if( $args['setting'][ $args['section'] ]['custom_price'] ): ?>
    <td>
        <?php
            wcwh_form_field( $prefixName.'[{i}][cprice]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '{cprice}', $view 
            ); 
        ?>
    </td>
    <?php endif; ?>
    <?php if( current_user_cans( ['discount_wh_sales_order'] ) ): ?>
    <td>
        <?php
            wcwh_form_field( $prefixName.'[{i}][foc]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                '{foc}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][discount]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['onfocusFocus','enterNextRow'] ], 
                '{discount}', $view 
            ); 
        ?>
    </td>
    <?php endif; ?>
    <td><a class="btn btn-sm btn-none-delete remove-row" data-remove=".row{i}" data-target="#item_row" title="Remove" ><i class="fa fa-trash-alt"></i></a></td>
</tr>
<?php 
    if( $needRemark ): 
    $colspan = 5;
    if( $args['setting'][ $args['section'] ]['custom_price'] ) $colspan+= 1;
    if( current_user_cans( ['discount_wh_sales_order'] ) ) $colspan+= 2;
?>
<tr class="row{i} follow_dragged">
    <td colspan="<?php echo $colspan ?>">
        <?php 
            wcwh_form_field( $prefixName.'[{i}][dremark]', 
                [ 'type'=>'textarea', 'required'=>false, 'attrs'=>[], 'class'=>['onfocusFocus'], 'placeholder'=>'Remark / Description' ], 
                '{dremark}', $view 
            ); 
        ?>
    </td>
</tr>
<?php endif; ?>