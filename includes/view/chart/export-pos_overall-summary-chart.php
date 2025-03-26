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
                <label class="" for="flag">Time Period</label><br>
                <?php
                    $options = [ 'day'=>'By Day', 'month'=>'By Month' ];
                        
                    wcwh_form_field( 'period', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>['data-showhide=".periods"'], 'class'=>['optionShowHide'],
                            'options'=> $options
                        ], 
                        isset( $filters['period'] )? $filters['period'] : '', $view 
                    ); 
                ?>
            </div>
            <div class="col form-group"></div>
        </div>

        <div class="form-row periods day">
            <div class="col form-group">
            <?php 
                $from_date = date( 'Y-m-d', strtotime( $filters['from_date'] ) );
                $def_from = date( 'm/d/Y', strtotime( $filters['from_date'] ) );

                wcwh_form_field( 'from_date', 
                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'From Date', 'required'=>false, 'class'=>['doc_date', 'picker'],
                        'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ]
                    ],  $from_date, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $to_date = date( 'Y-m-d', strtotime( $filters['to_date'] ) );
                $def_to = date( 'm/d/Y', strtotime( $filters['to_date'] ) );

                wcwh_form_field( 'to_date', 
                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'To Date', 'required'=>false, 'class'=>['doc_date', 'picker'], 
                        'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ]
                    ], $to_date, $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row periods month">
            <div class="col form-group">
            <?php 
                $from_date = date( 'Y-m', strtotime( $filters['from_date'] ) );

                wcwh_form_field( 'from_date_month', 
                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'From Month', 'required'=>false, 'class'=>['doc_date', 'picker'],
                        'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ]
                    ],  $from_date, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $to_date = date( 'Y-m', strtotime( $filters['to_date'] ) );

                wcwh_form_field( 'to_date_month', 
                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'To Month', 'required'=>false, 'class'=>['doc_date', 'picker'], 
                        'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ]
                    ], $to_date, $view 
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
                    [ 'id'=>'header', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>
        </div>
    </div>
    <?php
        wcwh_form_field( 'export_type', 
            [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
            'summary', $view 
        ); 

        wcwh_form_field( 'seller', 
            [ 'id'=>'seller', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
            $filters['seller'], $view 
        ); 
    ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>