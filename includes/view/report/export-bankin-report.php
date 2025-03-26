<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    $seller = $args['filters']['seller'];

    $doc_opts = $args['doc_opts'];

    $date_from = $filters['from_date'];
    $date_to = $filters['to_date'];
    if( ! $filters['from_date'] || ! $filters['to_date'] )
    {
        $date_from = current_time( 'Y-m-d' );
        $date_to = current_time( 'Y-m-d' );
    }

    $date_from = date( 'Y-m-d', strtotime( $date_from ) );
    $date_to = date( 'Y-m-d', strtotime( $date_to ) );
    
    $def_from = date( 'm/d/Y', strtotime( $date_from ) );
    $def_to = date( 'm/d/Y', strtotime( $date_to ) );
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
				$options =  [ 'doc_date'=>'Doc Created Date', 'post_date'=>' Doc Post Date' ];
                //'data-maximum-selection-length="50"'  ,'limitSelect' 'placeholder'=>'max 50 selection', 
                wcwh_form_field( 'date_type', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Date Type', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2','modalSelect'], 
                        'options'=> $options,
                    ], 
                    isset( $this->filters['date_type'] )? $this->filters['date_type'] : 'post_date', $view 
                ); 
            ?>
            </div>

            <div class="col form-group">
            <?php 
                wcwh_form_field( 'from_date', 
                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'From Date', 'required'=>false, 'class'=>['doc_date', 'picker'],
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ]
                    ],  $date_from, $view 
                ); 
            ?>
            </div>

            <div class="col form-group">
            <?php 
                wcwh_form_field( 'to_date', 
                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'To Date', 'required'=>false, 'class'=>['doc_date', 'picker'], 
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ]
                    ], $date_to, $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $currency = get_woocommerce_currencies();

                $options = options_data( $currency );
            
                wcwh_form_field( 'from_currency', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'From Currency', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                        'options'=> $options
                    ], 
                    isset( $this->filters['from_currency'] )? $this->filters['from_currency'] : 'MYR', $view 
                ); 
            ?>
            </div>

            <div class="col form-group">
            <?php 
                $currency = get_woocommerce_currencies();

                $options = options_data( $currency );
            
                wcwh_form_field( 'to_currency[]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'To Currency (Default: All)', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                    'options'=> $options, 'multiple'=>1
                ], 
                isset( $this->filters['to_currency'] )? $this->filters['to_currency'] : '', $view 
            );  
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = $doc_opts;
                //'data-maximum-selection-length="50"'  ,'limitSelect' 'placeholder'=>'max 50 selection', 
                wcwh_form_field( 'doc_id[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Doc No.', 'required'=>false, 'attrs'=>[], 
                        'class'=>['select2','modalSelect'], 
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['doc_id'], $view 
                ); 
            ?>
            </div>
        </div>

        <?php
            wcwh_form_field( 'export_type', 
                [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                'bankin', $view 
            ); 
    
            wcwh_form_field( 'seller', 
                [ 'id'=>'seller', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $seller, $view 
            );  
        ?>
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
                    'landscape', $view 
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