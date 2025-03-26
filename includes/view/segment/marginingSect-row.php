<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = !empty( $args['prefixName'] )? $args['prefixName'] : '_sect';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class="num"></td>
    <td>{title}
        <?php 
            wcwh_form_field( $prefixName.'[{i}][sub_section]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                '{sub_section}', $view 
            ); 

            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                '{item_id}', $view 
            );
           
        ?>
    </td>
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>