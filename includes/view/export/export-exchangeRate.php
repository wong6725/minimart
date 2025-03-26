<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>

    <div class='form-rows-group'>
        <h5>Filter</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( get_woocommerce_currencies() );

                wcwh_form_field( 'from_currency', 
                        [ 'id'=>'from_currency', 'type'=>'select', 'label'=>'From Currency', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ],
                    $datas['from_currency'], $view  
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                 $options = options_data( get_woocommerce_currencies() );

                 wcwh_form_field( 'to_currency', 
                         [ 'id'=>'to_currency', 'type'=>'select', 'label'=>'To Currency', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                         'options'=> $options
                     ], 
                     $datas['to_currency'], $view 
                 ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'from_date', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'From Effective Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['from_date'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'to_date', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'To Effective Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['to_date'], $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'all'=>'All', '1'=>'Ready', '0'=>'Trashed' ];

                wcwh_form_field( 'status', 
                    [ 'id'=>'status', 'type'=>'select', 'label'=>'Status', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    $datas['status'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = [ 'all'=> 'All', '0'=>'Pending', '1'=>'Approved', '-1'=>'Reject' ];

                wcwh_form_field( 'flag', 
                    [ 'id'=>'flag', 'type'=>'select', 'label'=>'Approval Status', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    $datas['flag'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

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
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>

            <?php 
                wcwh_form_field( 'export_type', 
                    [ 'id'=>'', 'type'=>'hidden', 'label'=>'Export Type', 'required'=>false, 'attrs'=>[], 'class'=>[] ],  
                    'default', $view 
                ); 
            ?>
        </div>
    </div>

	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>