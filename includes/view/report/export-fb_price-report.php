<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    
    $on_date = $filters['on_date'];
    if( ! $filters['on_date'] )
    {
        $on_date = current_time( 'Y-m-d' );
    }

    $on_date = date( 'Y-m-d', strtotime( $on_date ) );
    $def_date = date( 'm/d/Y', strtotime( $on_date ) );
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'on_date', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Price On Date', 'required'=>false, 'class'=>['doc_date', 'picker'],
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_date.'"' ]
                    ],  $on_date, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
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
                
                wcwh_form_field( 'client', 
                    [ 'id'=>'client', 'type'=>'select', 'label'=>'By Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
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
                
                wcwh_form_field( 'customer', 
                    [ 'id'=>'customer', 'type'=>'select', 'label'=>'By Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
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
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                if( $cats ) $filter['category'] = $cats;
                $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ], '' );
                
                wcwh_form_field( 'product[]', 
                    [ 'id'=>'product', 'type'=>'select', 'label'=>'By Item', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['product'], $view 
                ); 
            ?>
            </div>
        </div>

        <?php
            wcwh_form_field( 'export_type', 
                [ 'id'=>'export_type', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                'foodboard', $view 
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