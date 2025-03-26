<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    if( !$args['wh_code'])
    {
        if( $args['seller'] )
        {
            $warehouse = apply_filters( 'wcwh_get_warehouse', ['id'=>$args['seller'], 'status'=>1, 'visible'=>1], [], true, [ 'company'=>1 ] );
            $args['wh_code'] = $warehouse['code'];
        }
    }
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'to_date', 
                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'Until Date', 'required'=>false, 'class'=>['doc_date', 'picker'], 
                        'attrs'=>[ 'data-dd-format="Y-m-d"' ]
                    ], $filters['to_date'], $view 
                );

                wcwh_form_field( 'wh_code', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[]
                    ], 
                    $args['wh_code'], $view 
                );

                wcwh_form_field( 'seller', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[]
                    ], 
                    $args['seller'], $view 
                );

                wcwh_form_field( 'export_type', 
                [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                'stock_balance', $view 
            ); 
            ?>
            </div>

            <div class="col form-group">
                <?php
                $hour = [];
                for( $i = 0; $i <= 23; $i++ )
                {
                    $h = str_pad( $i, 2, "0", STR_PAD_LEFT );
                    $hh = date( 'h A', strtotime( " {$h}:00:00" ) );
                    $hour[ $i ] = $hh;
                }

                wcwh_form_field( 'to_hour', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Hour', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                        'options'=>$hour ], 
                    ( $filters['to_hour'] )? $filters['to_hour'] : '23', $view 
                );
                ?>                
            </div>

            <div class="col form-group">
                <?php
                $min = [];
                for( $i = 0; $i <= 50; $i+=5 )
                {
                    $m = str_pad( $i, 2, "0", STR_PAD_LEFT );
                    $min[ $i ] = $m;
                }

                wcwh_form_field( 'to_minute', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Minute', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                        'options'=>$min ], 
                    $filters['to_minute'], $view 
                );
                ?>                
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [ 'wh_code'=>$args['wh_code'] ];
                if( $args['seller'] ) 
                {
                    $filter['seller'] = $args['seller'];
                }
                $options = options_data( apply_filters( 'wcwh_get_storage', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );
                
                wcwh_form_field( 'strg_id', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Inventory Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    $filters['strg_id'], $view 
                ); 
            ?>
            </div>

            <?php if( $args['setting']['general']['use_item_storing_type'] ): ?>
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_store_type', $filter, [], false, [] ), 'id', [ 'code', 'name' ], 'Select', [ 'not'=>'Not Specify' ] );
                
                wcwh_form_field( 'store_type_id', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Storing Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $filters['store_type_id'], $view 
                ); 
            ?>
            </div>
            <?php endif; ?>

            <!--<div class="col form-group">
            <?php 
                $options = [ ''=>'All', '1'=>'Yes', '0'=>'No' ];
                
                wcwh_form_field( 'inconsistent_unit', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Inconsistent Metric(kg/l)', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $filters['inconsistent_unit'], $view 
                ); 
            ?>
            </div>-->
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item_group', $filter, [], false, [] ), 'id', [ 'code', 'name' ], '' );
                
                wcwh_form_field( 'grp_id[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Item Group', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                        'options'=> $options, 'multiple'=>1 
                    ], 
                    $filters['grp_id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_uom', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( '_uom_code[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By UOM', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                        'options'=> $options, 'multiple'=>1 
                    ], 
                    $filters['_uom_code'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'usage'=>0 ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
                
                wcwh_form_field( 'item_id[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Items', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                        'options'=> $options, 'multiple'=>1 
                    ], 
                    $filters['item_id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'term_id', [ 'slug', 'name' ], '' );

                wcwh_form_field( 'category[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Category', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                        'options'=> $options, 'multiple'=>1 
                    ], 
                    $filters['category'], $view 
                );
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ '' => 'All', 'yes' => 'Sellable', 'no' => "Not For Sale", 'force' => 'Force Sellable' ];
                
                wcwh_form_field( 'sellable', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Sellable', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $filters['sellable'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>

        <!--<div class="form-row">
            <div class="col form-group">
                <?php
                    $options = options_data( $args['default_column_title'], '', [], '' );
                
                    wcwh_form_field( 'd_column[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Export Column Selection', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                            'options'=> $options, 'multiple'=>1
                        ], 
                        $filters['d_column'], $view 
                    ); 
                ?>
            </div>
        </div>-->

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