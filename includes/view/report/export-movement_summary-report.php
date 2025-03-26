<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    
    $from_month = $filters['from_month'];
    if( ! $filters['from_month'] )
    {
        $from_month = date( 'Y-m', strtotime( date( current_time( 'Y-m' ) )." -1 month" ) );
    }

    $to_month = $filters['to_month'];
    if( ! $filters['to_month'] )
    {
        $to_month = current_time( 'Y-m' );
    }

    $from_month = date( 'Y-m', strtotime( $from_month ) );
    $to_month = date( 'Y-m', strtotime( $to_month ) );
    
    $def_fm = date( 'm/d/Y', strtotime( $from_month ) );
    $def_tm = date( 'm/d/Y', strtotime( $to_month ) );
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'from_month', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'From Month', 'required'=>false, 'class'=>['doc_date', 'picker'],
                        'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_fm.'"' ]
                    ],  $from_month, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'to_month', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'From Month', 'required'=>false, 'class'=>['doc_date', 'picker'],
                        'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$def_tm.'"' ]
                    ],  $to_month, $view 
                ); 
            ?>
            </div>
        </div>

        <?php
            wcwh_form_field( 'export_type', 
                [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                'movement_summary', $view 
            ); 

            wcwh_form_field( 'seller', 
                [ 'id'=>'seller', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $filters['seller'], $view 
            ); 
        ?>
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

    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>