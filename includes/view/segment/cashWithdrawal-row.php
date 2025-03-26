<?php
if ( !defined("ABSPATH") ) exit;
$load = $args['load'];
$prefixName = '_detail';
?>
<tr data-seq="{i}" data-id="{id}">
    <td class='num'></td>
      <td>
        <?php 
            if($load)
            {
                $value = '';
            }else
            {
                $value = '{bankin_person}';
            }
            wcwh_form_field( $prefixName.'[{i}][bankin_person]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['onfocusFocus','enterNextRow'], 'placeholder'=>'Name' ], 
                $value, $view
            ); 

        ?>
        <?php 
            wcwh_form_field( $prefixName.'[{i}][item_id]', 
                [ 'type'=>'hidden', 'required'=>true, 'attrs'=>[]], 
                '{item_id}', $view 
            ); 

        ?>
    </td>
    <td>
        <?php 
            if($load)
            {
                $value = '';
            }else
            {
                $value = '{bankin_date}';
            }
            wcwh_form_field( $prefixName.'[{i}][bankin_date]', 
            [ 'id'=>'', 'type'=>'text', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
            $value, $view
            ); 
            
        ?>
    </td>
    <td>
        <?php 
            if($load)
            {
                $value = '';
            }else
            {
                $value = '{bankin_amt}';
            }
            wcwh_form_field( $prefixName.'[{i}][bankin_amt]', 
                [ 'type'=>'text', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'], 'placeholder'=>'Amount' ], 
                $value, $view
            ); 

        ?>
    </td>

    
    <td><a class="btn btn-sm btn-none-delete remove-row" title="Remove"><i class="fa fa-trash-alt"></i></a></td>
</tr>