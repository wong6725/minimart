<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = !empty( $args['prefixName'] )? $args['prefixName'] : '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{client_info}
        <?php 
            wcwh_form_field( $prefixName.'[{i}][margin]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '{margin}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][client]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{client}', $view 
            );

            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{item_id}', $view 
            );
           
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>