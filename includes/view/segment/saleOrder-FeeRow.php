<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_fee';

?>
<tr data-seq="{i}" data-id="{id}" class="row{i} dragged_row">
    <td class="num handle"></td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][fee_name]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['onfocusFocus','enterNextRow'] ], 
                '{fee_name}', $view 
            ); 
        ?>
    </td>
    <td>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][fee]', 
                [ 'type'=>'text', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','onfocusFocus','enterNextRow'] ], 
                '{fee}', $view 
            ); 
        ?>
    </td>
    <td>
        <a class="btn btn-sm btn-none-delete remove-row " data-remove=".row{i}" data-target="#fee_row" title="Remove" ><i class="fa fa-trash-alt"></i></a>
    </td>
</tr>