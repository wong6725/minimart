<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
    <div class='form-rows-group'>
        <h5>Print Option</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'default'=>'Default A4' ];

                wcwh_form_field( 'paper_size', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Paper Size', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    'default', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                $options = [ '1'=>'HTML'];

                wcwh_form_field( 'html', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Print as', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    ($datas['html']) ? $datas['html'] : '1', $view 
                );
            ?>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="id" value="{id}" />
    <input type="hidden" name="action" value="{action}" />
    <input type="hidden" name="type" value="customer" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
    <input type="hidden" name="warehouse" value="<?php echo $args['warehouse']['id']; ?>" />
</form>