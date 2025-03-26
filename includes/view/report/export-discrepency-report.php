<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    $date_to = $filters['to_date'];
    if( ! $filters['to_date'] )
    {
        $date_to = current_time( 'Y-m-d' );
    }
    $date_to = date( 'Y-m-d', strtotime( $date_to ) );
    $def_to = date( 'm/d/Y', strtotime( $date_to ) );
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [];
                if( $filters['seller'] ) $filter['seller'] = $filters['seller'];

                $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );

                $opts = [];
                if( ! $wh['parent'] )
                {
                    if( $wh ) $outlets = apply_filters( 'wcwh_get_warehouse', [ 'parent'=>$wh['id'] ], [], false, [ 'meta'=>['client_company_code'] ] );

                    if( $outlets )
                    {
                        $filter['code'] = [];
                        foreach( $outlets as $outlet )
                        {
                            if( !empty( $outlet['client_company_code'] ) )
                            $filter['code'] = array_merge( $filter['code'], json_decode( stripslashes( $outlet['client_company_code'] ), true ) );
                        }

                        $filter['code'] = array_unique( $filter['code'] );
                        $opts = apply_filters( 'wcwh_get_client', $filter, [], false, [] );
                    }
                }
                else if( $filters['seller'] != $wh['id'] )
                {
                    $outlet = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filter['seller'] ], [], true, [ 'meta'=>['client_company_code'] ] );

                    $filter['code'] = json_decode( stripslashes( $outlet['client_company_code'] ), true );
                    $opts = apply_filters( 'wcwh_get_client', $filter, [], false, [] );
                }
                else
                {
                    $opts = apply_filters( 'wcwh_get_client', $filter, [], false, [] );
                }

                $options = options_data( $opts, 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( 'client[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Client', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['client'], $view 
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
                $options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, ['base'=>1] ), 'id', [ 'slug', 'name' ], '' );
                
                wcwh_form_field( 'category[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Category', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
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
                $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ], '' );
                
                wcwh_form_field( 'product[]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'By Item', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $filters['product'], $view 
                ); 
            ?>
            </div>
        </div>

        <?php
            wcwh_form_field( 'seller', 
                [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
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

    <input type="hidden" name="discrepancy_type" value="<?php echo $args['discrepancy_type']; ?>" />
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>