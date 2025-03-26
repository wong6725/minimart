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

    <div class="detail-container">
        <h5>Order Information</h5>
        <div class="form-row">
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['receipt']  ?></span>
                <label for="" class=" control-label">Order No.</label>    
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['time']  ?></span>
                <label for="" class=" control-label">Order Time</label>    
                <?php 
                    wcwh_form_field( $prefixName.'[id]', 
                        [ 'type'=>'hidden', 'required'=>true, 'attrs'=>[] ], 
                        $datas['id'], $view 
                    ); 
                    wcwh_form_field( $prefixName.'[total]', 
                        [ 'type'=>'hidden', 'required'=>true, 'attrs'=>[] ], 
                        $datas['total'], $view 
                    ); 
                ?>       
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['customer']  ?></span>
                <label for="" class=" control-label">Customer Name</label>    
            </div>
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['uid']  ?></span>
                <label for="" class=" control-label">Customer UID</label>    
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['paid_amount']  ?></span>
                <label for="" class=" control-label">Paid Amount</label>    
            </div>
            <div class="col form-group">
            <?php 
                 wcwh_form_field( $prefixName.'[amount]', 
                    [ 'type'=>'text', 'label'=>'Credit Amount','required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                    $datas['amount'], $view 
                ); 
            ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            </div>
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['total']  ?></span>
                <label for="" class=" control-label">Total Amount</label>    
            </div>
        </div>
    </>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>


