<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    
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
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                $wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'foodboard_client', 'foodboard_customer' ] ] );
                if( $wh )
                {
                    if( is_json( $detail['serial2'] ) ) $detail['serial2'] = json_decode( stripslashes( $detail['serial2'] ), true );
                    $Client = is_json( $wh['foodboard_client'] )? json_decode( stripslashes( $wh['foodboard_client'] ), true ) : $wh['foodboard_client'];
                    $Customer = is_json( $wh['foodboard_customer'] )? json_decode( stripslashes( $wh['foodboard_customer'] ), true ) : $wh['foodboard_customer'];
                }

                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                if( $Client ) $filter['code'] = $Client;
                $options = options_data( apply_filters( 'wcwh_get_client', $filter, [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( 'client[]', 
                    [ 'id'=>'client', 'type'=>'select', 'label'=>'By FoodBoard Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['client'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                if( $Customer ) $filter['id'] = $Customer;
                $options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
                
                wcwh_form_field( 'customer[]', 
                    [ 'id'=>'customer', 'type'=>'select', 'label'=>'By FoodBoard Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['customer'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                
                if( $args['setting']['foodboard_report']['categories'] )
                {
                    $c = [];
                    if( $filters['seller'] )
                    {
                        $self_cat = apply_filters( 'wcwh_get_item_category', [ 'id'=>$args['setting']['foodboard_report']['categories'] ], [], false );
                        if( $self_cat )
                        {
                            $slug = [];
                            foreach( $self_cat as $cat )
                            {
                                $slug[] = $cat['slug'];
                            }
                            
                            $f = [ 'slug'=>$slug, 'seller'=>$filters['seller'] ];
                            $outlet_cat = apply_filters( 'wcwh_get_item_category', $f, [], false );
                            
                            if( $outlet_cat )
                            {
                                foreach( $outlet_cat as $cat )
                                {
                                    $c[] = $cat['term_id'];
                                }
                            }
                        }
                    }
                    $filter['ancestor'] = $c;
                }

                $options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [ 'child'=>1 ] ), 'id', [ 'slug', 'name' ], '' );
                $cats = array_keys( $options );
                
                wcwh_form_field( 'category[]', 
                    [ 'id'=>'category', 'type'=>'select', 'label'=>'By Category', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['category'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
                
            </div>
        </div>

        <?php
            wcwh_form_field( 'export_type', 
                [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                'category', $view 
            ); 

            wcwh_form_field( 'seller', 
                [ 'id'=>'seller', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $filters['seller'], $view 
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