<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                $options = options_data( apply_filters( 'wcwh_get_account_type', $filter, [], false, ['sap_only'=>1] ), 'id', [ 'code' ], '' );
                
                wcwh_form_field( 'acc_type[]', 
                    [ 'id'=>'acc_type', 'type'=>'select', 'label'=>'Acc Type', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['acc_type'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer_job', $filter, [], false, [] ), 'id', [ 'name' ], '' );
                
                wcwh_form_field( 'cjob[]', 
                    [ 'id'=>'cjob', 'type'=>'select', 'label'=>'By Job / Position', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['cjob'], $view 
                ); 
            ?>
            </div>
        </div>
        
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, ['uid'=>3] ), 'id', [ 'code', 'uid', 'name' ], '' );
                
                wcwh_form_field( 'customer[]', 
                    [ 'id'=>'customer', 'type'=>'select', 'label'=>'By Customer', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['customer'], $view 
                ); 

                wcwh_form_field( 'export_type', 
                    [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    'credit_limit', $view 
                ); 
                wcwh_form_field( 'seller', 
                    [ 'id'=>'seller', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    $filters['seller'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php if( current_user_cans( [ 'save_wh_credit' ] ) ): ?>
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                $options = options_data( apply_filters( 'wcwh_get_customer_group', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'name' ], '' );
                
                wcwh_form_field( 'filter[cgroup][]', 
                    [ 'id'=>'cgroup', 'type'=>'select', 'label'=>'By Credit Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['cgroup'], $view 
                ); 
            ?>
            <?php endif; ?>
            </div>
        </div>
    </div>
<?php if( ! $args['isPrint'] ): ?>
    <div class='form-rows-group'>
        <h5>Export Option</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'xlsx'=>'Excel .xlsx', 'xls'=>'Excel .xls', 'csv'=>'Excel .csv', 'txt'=>'Text File .txt' ];

                wcwh_form_field( 'file_type', 
                    [ 'id'=>'file_type', 'type'=>'select', 'label'=>'File Type', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    'xlsx', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = [ ','=>',', '\t'=>'tab', ';'=>';', '|'=>'|', '||'=>'||' ];

                wcwh_form_field( 'delimiter', 
                    [ 'id'=>'delimiter', 'type'=>'select', 'label'=>'Delimiter', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 'description'=>'Apply for file with .csv or .txt extension',
                        'options'=>$options
                    ], 
                    ',', $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( 'header', 
                    [ 'id'=>'header', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class='form-rows-group'>
        <h5>Print Option</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'portrait'=>'Portrait', 'landscape'=>'Landscape' ];

                wcwh_form_field( 'orientation', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Orientation', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    'portrait', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'font_size', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Content Font Size (px)', 'required'=>false, 
                        'attrs'=>[], 'class'=>['numonly'], 
                    ], 
                    '9', $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'margin_top', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Margin Top', 'required'=>false, 
                        'attrs'=>[], 'class'=>['numonly'], 
                    ], 
                    '20', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'margin_bottom', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Margin Bottom', 'required'=>false, 
                        'attrs'=>[], 'class'=>['numonly'], 
                    ], 
                    '20', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'margin_left', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Margin Left', 'required'=>false, 
                        'attrs'=>[], 'class'=>['numonly'], 
                    ], 
                    '20', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'margin_right', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Margin Right', 'required'=>false, 
                        'attrs'=>[], 'class'=>['numonly'], 
                    ], 
                    '20', $view 
                ); 
            ?>
            </div>
        </div>
    </div>
<?php endif; ?>

    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>