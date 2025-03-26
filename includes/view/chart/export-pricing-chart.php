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
                $options = options_data( apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( 'seller', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Seller', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    $filters['seller'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="row">
            <?php
                $options = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                if( $options ):
            ?>
                <div class="segment col-md-4">
                    <label class="" for="flag">Client</label>
                    <?php
                        wcwh_form_field( 'client_code[]', 
                            [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                                'options'=> $options, "multiple"=>1
                            ], 
                            isset( $filters['client_code'] )? $filters['client_code'] : '', $view 
                        ); 
                    ?>
                </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label class="" for="flag">By Item </label><br>
                <?php
                    $filter = [];
                    if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                    $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>1 ] ), 'id', [ 'code', '_sku', 'name' ], '' );
                        
                    wcwh_form_field( 'product[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                                'options'=> $options, 'multiple'=>1
                        ], 
                        isset( $filters['product'] )? $filters['product'] : '', $view 
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

	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>