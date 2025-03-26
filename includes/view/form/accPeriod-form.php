<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="header-container">
        <h5>Header</h5>
        
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_warehouse', ['status'=>1 ], [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
                if( !empty( $args['wh_code'] ) )
                {
                    wcwh_form_field( $prefixName.'[warehouse_id]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'Company / Outlet', 'required'=>false, 'attrs'=>[] ], 
                        $options[ $args['wh_code'] ], true 
                    );  
                    wcwh_form_field( $prefixName.'[warehouse_id]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                         $args['wh_code'], $view
                    );  
                }
                else
                {
                    wcwh_form_field( $prefixName.'[warehouse_id]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Company / Outlet', 'required'=>true, 'attrs'=>[], 'class'=>[ 'select2Strict' ],
                            'options'=> $options
                        ], 
                        ( $args['wh_code'] )? $args['wh_code'] : $datas['warehouse_id'], $view 
                    ); 
                }
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Closing Date', 'required'=>true, 'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : '', $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[remark]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remark'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <?php if( ! empty( $args['render'] ) ): ?>
    <div class="detail-container">
        <h5>Details</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                echo $args['render'];
            ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if( $datas['doc_id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>