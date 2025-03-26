<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_attachment';
?>
<tr>
    <td style="vertical-align: top;text-align: left; width:40%;">
        <?php 
            echo wcwh_render_attachment( $args['id'], ['photo'=>'180px'], $args ); 
        ?>
    </td>
    <td style="vertical-align: top;text-align: left; width:40%;">
        <?php 
            echo wcwh_render_attachment( $args['id'], [], $args ); 

            wcwh_form_field( $prefixName.'['.$args['i'].'][attach_id]', 
                [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                $args['id'], $view 
            ); 
        ?>
    </td>
    <td style="vertical-align: top;text-align: right;">
    <?php if( ! $args['view'] ): ?>
        <a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a>
    <?php endif; ?>
    </td>
</tr>